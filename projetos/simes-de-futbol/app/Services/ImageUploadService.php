<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;

final class ImageUploadService
{
    /** @var array<string,string> */
    private const ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * Valida e armazena uma imagem enviada por upload.
     *
     * @param array<string,mixed>|null $file
     * @return array{ok:bool,message:string,filename?:string}
     */
    public static function store(?array $file, string $subdir = 'products'): array
    {
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['ok' => false, 'message' => 'Nenhum arquivo enviado.'];
        }

        $error = (int) ($file['error'] ?? UPLOAD_ERR_OK);
        if ($error !== UPLOAD_ERR_OK) {
            $message = match ($error) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'A imagem excede o tamanho máximo permitido.',
                UPLOAD_ERR_PARTIAL => 'O upload da imagem não foi concluído.',
                default => 'Falha no upload da imagem.',
            };

            return ['ok' => false, 'message' => $message];
        }

        $maxBytes = (int) Config::get('app.upload_max_bytes', 3 * 1024 * 1024);
        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > $maxBytes) {
            return ['ok' => false, 'message' => 'A imagem deve ter no máximo ' . round($maxBytes / 1024 / 1024, 1) . ' MB.'];
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return ['ok' => false, 'message' => 'Arquivo de upload inválido.'];
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp);
        if (!is_string($mime) || !isset(self::ALLOWED[$mime])) {
            return ['ok' => false, 'message' => 'Formato não permitido. Envie JPG, PNG ou WebP.'];
        }

        $info = @getimagesize($tmp);
        if ($info === false || ($info['mime'] ?? '') !== $mime) {
            return ['ok' => false, 'message' => 'O arquivo não é uma imagem válida.'];
        }

        $extension = self::ALLOWED[$mime];
        $subdir = preg_replace('/[^a-z0-9_-]/', '', $subdir) ?: 'products';
        $filename = $subdir . '/' . date('Ymd') . '-' . bin2hex(random_bytes(10)) . '.' . $extension;
        $destination = BASE_PATH . '/public/uploads/' . $filename;

        $dir = dirname($destination);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return ['ok' => false, 'message' => 'Não foi possível criar a pasta de imagens.'];
        }

        if (!move_uploaded_file($tmp, $destination)) {
            return ['ok' => false, 'message' => 'Não foi possível salvar a imagem.'];
        }

        @chmod($destination, 0644);

        return ['ok' => true, 'message' => 'Imagem enviada com sucesso.', 'filename' => $filename];
    }

    public static function delete(?string $filename): void
    {
        if ($filename === null || trim($filename) === '') {
            return;
        }

        $clean = str_replace(['..', '\\'], '', $filename);
        $path = BASE_PATH . '/public/uploads/' . ltrim($clean, '/');
        $real = realpath($path);
        $uploadsRoot = realpath(BASE_PATH . '/public/uploads');

        if ($real === false || $uploadsRoot === false || !str_starts_with($real, $uploadsRoot)) {
            return;
        }

        if (is_file($real)) {
            @unlink($real);
        }
    }
}
