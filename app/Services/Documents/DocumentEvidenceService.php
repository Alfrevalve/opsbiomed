<?php

namespace App\Services\Documents;

use App\Models\DocumentEvidence;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class DocumentEvidenceService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function upload(array $data, Model $documentable, User $user): DocumentEvidence
    {
        $file = $data['file'] ?? null;
        $path = null;

        try {
            return DB::transaction(function () use ($data, $documentable, $user, $file, &$path): DocumentEvidence {
                if (! $file instanceof UploadedFile && blank($data['link_url'] ?? null)) {
                    throw new DomainException('Debe adjuntar un archivo o registrar una referencia.');
                }

                if ($file instanceof UploadedFile) {
                    $path = $file->store('document-evidences/'.(string) $data['document_type'], 'private');
                }

                $document = DocumentEvidence::create([
                    'documentable_type' => $documentable->getMorphClass(),
                    'documentable_id' => $documentable->getKey(),
                    'document_type' => $data['document_type'],
                    'title' => $data['title'],
                    'description' => $data['description'] ?? null,
                    'file_path' => $path,
                    'link_url' => $data['link_url'] ?? null,
                    'mime_type' => $file instanceof UploadedFile ? $file->getClientMimeType() : null,
                    'file_size' => $file instanceof UploadedFile ? $file->getSize() : null,
                    'uploaded_by' => $user->id,
                    'document_date' => $data['document_date'] ?? null,
                    'is_required' => (bool) ($data['is_required'] ?? false),
                    'status' => 'cargado',
                ]);

                $this->auditLogger->record('document.uploaded', $document, [], $this->snapshot($document));

                return $document;
            });
        } catch (Throwable $exception) {
            if ($path !== null) {
                Storage::disk('private')->delete($path);
            }

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function validateDocument(DocumentEvidence $document, array $data, User $user): DocumentEvidence
    {
        return DB::transaction(function () use ($document, $data, $user): DocumentEvidence {
            $lockedDocument = DocumentEvidence::query()->lockForUpdate()->findOrFail($document->id);
            $before = $this->snapshot($lockedDocument);
            $status = (string) $data['status'];

            $lockedDocument->update([
                'status' => $status,
                'validation_observations' => $data['validation_observations'] ?? null,
                'validated_by' => $user->id,
                'validated_at' => now(),
            ]);

            $action = $status === 'rechazado' ? 'document.rejected' : 'document.validated';
            $this->auditLogger->record($action, $lockedDocument, $before, $this->snapshot($lockedDocument));

            return $lockedDocument->refresh();
        });
    }

    public function delete(DocumentEvidence $document, User $user): void
    {
        DB::transaction(function () use ($document, $user): void {
            $lockedDocument = DocumentEvidence::query()->lockForUpdate()->findOrFail($document->id);
            $before = $this->snapshot($lockedDocument);
            $lockedDocument->delete();

            $this->auditLogger->record('document.deleted', $lockedDocument, $before, [
                'deleted_at' => $lockedDocument->deleted_at?->toISOString(),
                'deleted_by' => $user->id,
                'file_path_retained' => $lockedDocument->file_path !== null,
            ]);
        });
    }

    /** @return array<string, mixed> */
    private function snapshot(DocumentEvidence $document): array
    {
        return [
            'id' => $document->id,
            'documentable_type' => $document->documentable_type,
            'documentable_id' => $document->documentable_id,
            'document_type' => $document->document_type,
            'title' => $document->title,
            'file_path' => $document->file_path,
            'link_url' => $document->link_url,
            'mime_type' => $document->mime_type,
            'file_size' => $document->file_size,
            'uploaded_by' => $document->uploaded_by,
            'status' => $document->status,
            'validated_by' => $document->validated_by,
            'validated_at' => $document->validated_at?->toISOString(),
        ];
    }
}
