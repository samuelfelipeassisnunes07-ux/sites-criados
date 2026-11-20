<?php
// Router exclusivo do servidor local PHP. A raiz pública nunca inclui src/, var/ ou runtime/.
declare(strict_types=1);
$path=rawurldecode(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH) ?: '/');
$public=realpath(__DIR__.'/public');
if(str_contains($path,"\0") || str_contains($path,'\\') || preg_match('~(?:^|/)\.~',$path)) { http_response_code(404);exit('Página não encontrada.'); }
if($path==='/') { require $public.'/index.php';return true; }
$file=realpath($public.$path);
if($file && str_starts_with($file,$public.DIRECTORY_SEPARATOR) && is_file($file)) return false;
http_response_code(404);header('Content-Type: text/plain; charset=utf-8');echo 'Página não encontrada.';
