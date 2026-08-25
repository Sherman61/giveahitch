<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../lib/session.php';
require_once __DIR__ . '/../../lib/auth.php';
use function App\Auth\{require_admin, csrf_verify};
header('Content-Type: application/json'); start_secure_session(); require_admin();
$in = $_POST ?: json_decode(file_get_contents('php://input'), true) ?: []; csrf_verify((string)($in['csrf'] ?? ''));
$id = (int)($in['ride_id'] ?? 0); $pdo = db();
$q=$pdo->prepare('SELECT id,type,from_text,to_text,note,phone,whatsapp,whatsapp_sent FROM rides WHERE id=? AND COALESCE(deleted,0)=0 LIMIT 1'); $q->execute([$id]); $ride=$q->fetch();
if (!$ride) { http_response_code(404); echo json_encode(['ok'=>false,'error'=>'Ride not found']); exit; }
if ((int)$ride['whatsapp_sent']) { echo json_encode(['ok'=>false,'error'=>'Ride was already sent']); exit; }
$ch=curl_init('http://127.0.0.1:3000/publish-ride'); curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($ride),CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15]); $raw=curl_exec($ch); $status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch); $out=json_decode((string)$raw,true);
if ($status!==200 || empty($out['ok']) || empty($out['messageId'])) { http_response_code(502); echo json_encode(['ok'=>false,'error'=>$out['error']??'WhatsApp delivery failed']); exit; }
$pdo->prepare('UPDATE rides SET whatsapp_sent=1, whatsapp_sent_at=NOW(), whatsapp_message_id=? WHERE id=? AND whatsapp_sent=0')->execute([$out['messageId'],$id]);
echo json_encode(['ok'=>true,'messageId'=>$out['messageId']]);
