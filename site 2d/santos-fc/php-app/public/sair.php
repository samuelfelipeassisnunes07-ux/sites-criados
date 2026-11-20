<?php
require __DIR__.'/../src/bootstrap.php';
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);header('Allow: POST');exit('Use o botão Sair na sua conta.');}
try { post_guard();$s=current_session();if($s){if($s['user_id'])audit((int)$s['user_id'],'logout',(int)$s['user_id']);query('DELETE FROM sessions WHERE token_hash=?',[$s['token_hash']]);}clear_auth_cookie();redirect('/login.php'); }
catch(FormError $ex){http_response_code(403);echo e($ex->getMessage());}
