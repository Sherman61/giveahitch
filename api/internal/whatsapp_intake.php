<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
$env = @parse_ini_file('/etc/whatsapp-bridge.env');
if (!$env || !hash_equals((string)($env['BRIDGE_INTERNAL_TOKEN'] ?? ''), (string)($_SERVER['HTTP_X_BRIDGE_TOKEN'] ?? ''))) { http_response_code(403); exit; }
require_once __DIR__ . '/../../config/db.php';
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $input['action'] ?? 'intake'; $pdo = db();
function output(array $data, int $code = 200): never { http_response_code($code); echo json_encode($data); exit; }
function mode(PDO $pdo): string { $v=$pdo->query("SELECT setting_value FROM whatsapp_bridge_settings WHERE setting_key='intake_mode'")->fetchColumn(); return $v==='automatic'?'automatic':'manual'; }
function workflow(PDO $pdo): array { $rows=$pdo->query("SELECT setting_key,setting_value FROM whatsapp_bridge_settings WHERE setting_key LIKE 'trigger_%' OR setting_key LIKE 'condition_%' OR setting_key LIKE 'action_%' OR setting_key LIKE 'keyword_%' OR setting_key IN ('format_help','bot_mention')")->fetchAll(PDO::FETCH_KEY_PAIR); $defaults=['trigger_mention'=>1,'trigger_all_group'=>0,'trigger_confirm'=>1,'trigger_private_mention'=>1,'condition_format'=>1,'condition_confirm'=>1,'action_private_dm'=>1,'action_queue'=>1,'action_post_group'=>1,'action_create_ride'=>1]; foreach($defaults as $k=>$v)$defaults[$k]=($rows[$k]??(string)$v)==='1'; $defaults['bot_mention']=$rows['bot_mention']??'@ridebot'; $defaults['keywords']=['looking'=>$rows['keyword_looking']??'looking,need,request','offering'=>$rows['keyword_offering']??'offering,offer,drive,available','from'=>$rows['keyword_from']??'from','to'=>$rows['keyword_to']??'to','going_to'=>$rows['keyword_going_to']??'going to,going-to','call'=>$rows['keyword_call']??'call','whatsapp'=>$rows['keyword_whatsapp']??'whatsapp','note'=>$rows['keyword_note']??'note']; $defaults['format_help']=$rows['format_help']??'@ridebot Looking from Kingston to Montego Bay call: 876-555-1234 whatsapp: 876-555-9999 note: Need to arrive before 5 PM.'; return $defaults; }
if ($action === 'workflow') output(['ok'=>true,'workflow'=>workflow($pdo)]);
if ($action === 'intake') {
  $r = $input['ride'] ?? []; $jid=(string)($r['groupJid']??''); $sender=(string)($r['senderJid']??''); $mid=(string)($r['messageId']??'');
  if (!$jid || !$sender || !$mid) output(['ok'=>false,'error'=>'Missing message identity'],422);
  $stmt=$pdo->prepare('INSERT INTO whatsapp_ride_intakes (source_group_jid,source_sender_jid,source_message_id,raw_text,ride_type,from_text,to_text,note,phone,whatsapp,intake_status) VALUES (?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)');
  $complete= !empty($r['type']) && !empty($r['from_text']) && !empty($r['to_text']) && (!empty($r['phone'])||!empty($r['whatsapp']));
  $status=$complete?'awaiting_confirmation':'needs_details';
  $stmt->execute([$jid,$sender,$mid,(string)($r['text']??''),$r['type']??null,$r['from_text']??null,$r['to_text']??null,$r['note']??null,$r['phone']??null,$r['whatsapp']??null,$status]);
  output(['ok'=>true,'id'=>(int)$pdo->lastInsertId(),'mode'=>mode($pdo),'complete'=>$complete,'status'=>$status,'workflow'=>workflow($pdo)]);
}
if ($action === 'pending_for_sender') {
  $s=$pdo->prepare("SELECT * FROM whatsapp_ride_intakes WHERE source_sender_jid=? AND intake_status IN ('needs_details','awaiting_confirmation') ORDER BY id DESC LIMIT 1"); $s->execute([(string)$input['senderJid']]); output(['ok'=>true,'intake'=>$s->fetch()?:null,'mode'=>mode($pdo),'workflow'=>workflow($pdo)]);
}
if ($action === 'mark_confirmed') { $pdo->prepare("UPDATE whatsapp_ride_intakes SET intake_status='confirmed' WHERE id=?")->execute([(int)$input['id']]); output(['ok'=>true]); }
if ($action === 'cancel') { $pdo->prepare("UPDATE whatsapp_ride_intakes SET intake_status='cancelled' WHERE id=?")->execute([(int)$input['id']]); output(['ok'=>true]); }
if ($action === 'mark_posted') { $q=$pdo->prepare('SELECT * FROM whatsapp_ride_intakes WHERE id=?');$q->execute([(int)$input['id']]);$r=$q->fetch();if(!$r)output(['ok'=>false],404);if(!empty($input['createRide']))$pdo->prepare("INSERT INTO rides (user_id,type,from_text,to_text,note,phone,whatsapp,status,deleted,created_via,whatsapp_sent,whatsapp_sent_at,whatsapp_message_id,created_at,updated_at) VALUES (NULL,?,?,?,?,?,?, 'open',0,'whatsapp_bot',1,NOW(),?,NOW(),NOW())")->execute([$r['ride_type'],$r['from_text'],$r['to_text'],$r['note'],$r['phone'],$r['whatsapp'],$input['messageId']??null]);$pdo->prepare("UPDATE whatsapp_ride_intakes SET intake_status='posted', last_error=NULL WHERE id=?")->execute([(int)$input['id']]); output(['ok'=>true]); }
output(['ok'=>false,'error'=>'Unknown action'],400);
