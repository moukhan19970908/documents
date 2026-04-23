<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\Department;
use App\Models\DocumentType;
use App\Models\DocumentFile;
use Illuminate\Http\Request;

class ArchiveController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->get('tab', 'all');
        $folder = $request->get('folder');

        $query = Document::with(['type', 'initiator', 'currentFile'])
            ->whereIn('status', ['approved', 'signed', 'archived'])
            ->orderByDesc('updated_at');

        if ($tab === 'approved') {
            $query->whereIn('status', ['approved', 'signed']);
        } elseif ($tab === 'rejected') {
            $query->whereHas('approvals', fn($q) => $q->where('status', 'rejected'));
        }

        if ($search = $request->get('search')) {
            $query->where('title', 'like', "%{$search}%");
        }

        if ($dateFrom = $request->get('date_from')) {
            $query->whereDate('updated_at', '>=', $dateFrom);
        }

        if ($dateTo = $request->get('date_to')) {
            $query->whereDate('updated_at', '<=', $dateTo);
        }

        if ($author = $request->get('author')) {
            $query->where('initiator_id', $author);
        }

        if ($department = $request->get('department')) {
            $query->whereHas('initiator', fn($q) => $q->where('department_id', $department));
        }

        if ($type = $request->get('type')) {
            $query->where('document_type_id', $type);
        }

        $documents = $query->paginate(25)->withQueryString();
        $departments = Department::all();
        $documentTypes = DocumentType::all();

        // Build folder tree from document types
        $folderTree = DocumentType::withCount(['documents' => fn($q) => $q->whereIn('status', ['approved', 'signed', 'archived'])])->get();

        $storageUsed = DocumentFile::sum('file_size');
        $storageTotal = 10 * 1024 * 1024 * 1024; // 10 GB
        $storagePercent = min(100, round($storageUsed / $storageTotal * 100));

        return view('archive.index', compact('documents', 'departments', 'documentTypes', 'folderTree', 'storagePercent', 'storageUsed', 'tab'));
    }
}
