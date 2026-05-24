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

        $content = $this->extractDocxHtml(public_path('aggreement.docx'));

        return view('agreement.show', compact('content'));
    }

    private function extractDocxHtml(string $path): string
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

        $dom = new \DOMDocument();
        $dom->loadXML($xml);
        $xp = new \DOMXPath($dom);
        $xp->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $paragraphs = $xp->query('//w:body/w:p');

        $html    = '';
        $inList  = false;
        $subList = false; // nested sub-list open

        foreach ($paragraphs as $para) {
            $pPr      = $xp->query('w:pPr', $para)->item(0);
            $isTitle  = false;
            $isList   = false;
            $isSubList = false;

            if ($pPr) {
                // Title: has outlineLvl (heading)
                if ($xp->query('w:outlineLvl', $pPr)->length > 0) {
                    $isTitle = true;
                }
                // List item
                $numPr = $xp->query('w:numPr', $pPr)->item(0);
                if ($numPr) {
                    $isList   = true;
                    $numIdEl  = $xp->query('w:numId', $numPr)->item(0);
                    $numId    = $numIdEl ? (int) $numIdEl->getAttribute('w:val') : 1;
                    $isSubList = ($numId === 2); // sub-items use numId=2
                }
            }

            // Build run HTML (preserving bold)
            $runHtml = '';
            foreach ($xp->query('w:r', $para) as $run) {
                $rPr  = $xp->query('w:rPr', $run)->item(0);
                $bold = false;
                if ($rPr) {
                    $bEl  = $xp->query('w:b', $rPr)->item(0);
                    $bold = $bEl && $bEl->getAttribute('w:val') !== 'false' && $bEl->getAttribute('w:val') !== '0';
                }
                $text = '';
                foreach ($xp->query('w:t', $run) as $t) {
                    $text .= $t->textContent;
                }
                $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
                $runHtml .= $bold ? "<strong>{$text}</strong>" : $text;
            }

            // Empty paragraph = spacer
            if (trim($runHtml) === '') {
                if ($subList) { $html .= '</ul>'; $subList = false; }
                if ($inList)  { $html .= '</ul>'; $inList  = false; }
                continue;
            }

            if ($isTitle) {
                if ($subList) { $html .= '</ul>'; $subList = false; }
                if ($inList)  { $html .= '</ul>'; $inList  = false; }
                $html .= "<h1 class=\"doc-title\">{$runHtml}</h1>";
            } elseif ($isList && $isSubList) {
                // Sub-list item
                if (!$inList) { $html .= '<ul class="doc-list">'; $inList = true; }
                if (!$subList) { $html .= '<ul class="doc-sublist">'; $subList = true; }
                $html .= "<li>{$runHtml}</li>";
            } elseif ($isList) {
                // Main list item
                if ($subList) { $html .= '</ul>'; $subList = false; }
                if (!$inList) { $html .= '<ul class="doc-list">'; $inList = true; }
                $html .= "<li>{$runHtml}</li>";
            } else {
                // Regular paragraph
                if ($subList) { $html .= '</ul>'; $subList = false; }
                if ($inList)  { $html .= '</ul>'; $inList  = false; }
                $html .= "<p class=\"doc-para\">{$runHtml}</p>";
            }
        }

        if ($subList) { $html .= '</ul>'; }
        if ($inList)  { $html .= '</ul>'; }

        return $html;
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

        return redirect()->route('login')->with('error', 'Р”Р»СЏ РёСЃРїРѕР»СЊР·РѕРІР°РЅРёСЏ СЃРёСЃС‚РµРјС‹ РЅРµРѕР±С…РѕРґРёРјРѕ РїСЂРёРЅСЏС‚СЊ СЃРѕРіР»Р°С€РµРЅРёРµ.');
    }
}
