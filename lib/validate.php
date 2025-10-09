<?php
declare(strict_types=1);

function validate_ride(array $in): array {
    $errors = [];
    $data = [];

    $type = strtolower(trim((string)($in['type'] ?? '')));
    if (!in_array($type, ['offer','request'], true)) {
        $errors['type'] = 'Type must be offer or request.'; 
    }
    $data['type'] = $type;

    $from = trim((string)($in['from_text'] ?? ''));
    $to   = trim((string)($in['to_text']   ?? ''));
    if (mb_strlen($from) < 2 || mb_strlen($from) > 255) $errors['from_text']='Enter a valid From location.';
    if (mb_strlen($to)   < 2 || mb_strlen($to)   > 255) $errors['to_text']='Enter a valid To location.';
    $data['from_text']=$from; $data['to_text']=$to;

    $seats = (int)($in['seats'] ?? 1);
    if ($seats < 0 || $seats > 15) $errors['seats']='Seats must be 0-15.';
    $data['seats'] = $seats;

    $package_only = ($seats === 0) ? 1 : (int)($in['package_only'] ?? 0);
    $data['package_only'] = $package_only;

    $note = trim((string)($in['note'] ?? ''));
    if (mb_strlen($note) > 1000) $errors['note']='Note too long.';
    $data['note'] = $note;

    $phone    = trim((string)($in['phone'] ?? ''));
    $whatsapp = trim((string)($in['whatsapp'] ?? ''));
    $re = '/^\+?[0-9\s\-\(\)]{7,20}$/';
    if ($phone !== '' && !preg_match($re, $phone))       $errors['phone']='Invalid phone number.';
    if ($whatsapp !== '' && !preg_match($re, $whatsapp)) $errors['whatsapp']='Invalid WhatsApp.';
    if ($phone === '' && $whatsapp === '') $errors['contact']='Provide phone or WhatsApp.';
    $data['phone']=$phone; $data['whatsapp']=$whatsapp;

    $ride_datetime = null;
    if (!empty($in['ride_datetime'])) {
        $dt = \DateTime::createFromFormat('Y-m-d\TH:i', (string)$in['ride_datetime']);
        if ($dt === false) $errors['ride_datetime']='Invalid date/time.';
        else $ride_datetime = $dt->format('Y-m-d H:i:s');
    }
    $data['ride_datetime']=$ride_datetime;

    // Basic anti-bot honeypot: hidden field must be empty
    if (!empty($in['website'])) $errors['bot']='Bot detected.';

    return [$errors, $data];
}
?>