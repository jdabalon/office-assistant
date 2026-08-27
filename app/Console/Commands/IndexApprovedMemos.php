<?php

namespace App\Console\Commands;

use App\Models\Document;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use ZipArchive;

class IndexApprovedMemos extends Command
{
    protected $signature = 'knowledge:index-memos';

    protected $description = 'Index approved memorandum files';

    public function handle(): int
    {
        $path = config('services.knowledge.memos_path');

        if (! is_dir($path)) {
            $this->error("Folder not found: {$path}");

            return self::FAILURE;
        }

        $files = File::files($path);

        foreach ($files as $file) {
            if (strtolower($file->getExtension()) !== 'docx') {
                continue;
            }

            $document = Document::updateOrCreate(
                [
                    'file_path' => $file->getPathname(),
                ],
                [
                    'title' => pathinfo(
                        $file->getFilename(),
                        PATHINFO_FILENAME
                    ),
                    'file_name' => $file->getFilename(),
                    'document_type' => 'memorandum',
                    'status' => 'approved',
                ]
            );

            $text = $this->extractDocxText(
                $file->getPathname()
            );

            if (blank($text)) {
                $this->warn(
                    "No text extracted: {$file->getFilename()}"
                );

                continue;
            }

            // Remove old chunks before rebuilding.
            $document->chunks()->delete();

            $chunks = $this->chunkText($text);

            foreach ($chunks as $index => $chunk) {
                $document->chunks()->create([
                    'chunk_index' => $index,
                    'content' => $chunk,
                ]);
            }

            $document->update([
                'indexed_at' => now(),
            ]);

            $this->info(
                "Indexed: {$file->getFilename()} (" .
                count($chunks) .
                " chunks)"
            );
        }

        return self::SUCCESS;
    }

    private function extractDocxText(string $path): string
    {
        $zip = new ZipArchive();

        if ($zip->open($path) !== true) {
            return '';
        }

        $xml = $zip->getFromName('word/document.xml');

        $zip->close();

        if (! $xml) {
            return '';
        }

        // Preserve paragraph and table-row breaks.
        $xml = str_replace(
            [
                '</w:p>',
                '</w:tr>',
                '<w:tab/>',
                '<w:br/>',
            ],
            [
                "\n",
                "\n",
                "\t",
                "\n",
            ],
            $xml
        );

        $text = strip_tags($xml);

        // Decode XML/HTML entities.
        $text = html_entity_decode(
            $text,
            ENT_QUOTES | ENT_XML1,
            'UTF-8'
        );

        // Normalize spaces without destroying paragraphs.
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }

    private function chunkText(
        string $text,
        int $chunkSize = 1200,
        int $overlap = 200
    ): array {
        $chunks = [];

        $length = mb_strlen($text);
        $start = 0;

        while ($start < $length) {
            $chunk = mb_substr(
                $text,
                $start,
                $chunkSize
            );

            $chunk = trim($chunk);

            if ($chunk !== '') {
                $chunks[] = $chunk;
            }

            $start += $chunkSize - $overlap;
        }

        return $chunks;
    }
}