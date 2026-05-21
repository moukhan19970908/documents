<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AgreementController extends Controller
{
    public function show()
    {
        if (Auth::user()->agreement_accepted_at) {
            return redirect()->route('dashboard');
        }

        $content = $this->extractDocxText(public_path('aggreement.docx'));

        return view('agreement.show', compact('content'));
    }

    private function extractDocxText(string $path): string
    {
        if (!file_exists($path)) {
            return '';
        }

        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return '';
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if (!$xml) {
            return '';
        }

        // Parse paragraphs preserving line breaks
        $doc = simplexml_load_string($xml);
        $doc->registerXPathNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $paragraphs = $doc->xpath('//w:p');
        $lines = [];
        foreach ($paragraphs as $para) {
            $para->registerXPathNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
            $nodes = $para->xpath('.//w:t');
            $line = implode('', array_map(fn($t) => (string) $t, $nodes));
            $lines[] = $line;
        }

        return implode("\n", $lines);
    }

    public function accept(Request $request)
    {
        $request->validate([
            'accepted' => ['required', 'accepted'],
        ]);

        $user = Auth::user();
        $now  = now();

        $user->update(['agreement_accepted_at' => $now]);

        AuditLog::create([
            'user_id'    => $user->id,
            'action'     => 'agreement_accepted',
            'model_type' => null,
            'model_id'   => null,
            'old_values' => null,
            'new_values' => ['accepted_at' => $now->toIso8601String()],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->intended(route('dashboard'));
    }

    public function decline()
    {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login')->with('error', 'Для использования системы необходимо принять соглашение.');
    }
}
