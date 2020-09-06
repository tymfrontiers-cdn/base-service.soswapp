<?php
namespace TymFrontiers;
require_once "../.appinit.php";
use \TymFrontiers\HTTP\Header,
    \Mailgun\Mailgun;
\header("Content-Type: application/json");
$post = \json_decode( \file_get_contents('php://input'), true); // json data
$post = !empty($post) ? $post : (
  !empty($_POST) ? $_POST : $_GET
);
$gen = new Generic;
$auth = new API\Authentication ($api_sign_patterns);
$http_auth = $auth->validApp ();
if ( !$http_auth && ( empty($post['form']) || empty($post['CSRF_token']) ) ){
  HTTP\Header::unauthorized (false,'', Generic::authErrors ($auth,"Request [Auth-App]: Authetication failed.",'self',true));
}

$rqp = [
  "limit" => ["limit","int"],

  "form" => ["form","text",2,72],
  "CSRF_token" => ["CSRF_token","text",5,1024]
];
$req = [];
if (!$http_auth) {
  $req[] = 'form';
  $req[] = 'CSRF_token';
}

$params = $gen->requestParam($rqp,$post,$req);
if (!$params || !empty($gen->errors)) {
  $errors = (new InstanceError($gen,true))->get("requestParam",true);
  echo \json_encode([
    "status" => "3." . \count($errors),
    "errors" => $errors,
    "message" => "Request halted"
  ]);
  exit;
}
if( !$http_auth ){
  if ( !$gen->checkCSRF($params["form"],$params["CSRF_token"]) ) {
    $errors = (new InstanceError($gen,true))->get("checkCSRF",true);
    echo \json_encode([
      "status" => "3." . \count($errors),
      "errors" => $errors,
      "message" => "Request halted."
    ]);
    exit;
  }
}
// Begin dev process
$GLOBALS["database"]->closeConnection();
$GLOBALS["database"] = new \TymFrontiers\MySQLDatabase(MYSQL_SERVER, MYSQL_DEVELOPER_USERNAME, MYSQL_DEVELOPER_PASS);
$limit = $params['limit'] > 0 ? $params['limit'] : 200;
$send_errors = [];
$mails = (new MultiForm(MYSQL_LOG_DB,'email_outbox','id'))->findBySql("SELECT * FROM :db:.:tbl: WHERE status ='Q' ORDER BY priority ASC, _created DESC LIMIT {$limit}");
if( $mails ):
  $batches = [];
  $ids = [];
  $attachments = [];
  foreach($mails as $eml){
    if( (bool)$eml->has_attachment )  $batches[] = $eml->batch;
  }
  $batches = \array_unique($batches);
  $log_db = MYSQL_LOG_DB;
  $base_db = MYSQL_BASE_DB;
  $file_db = MYSQL_FILE_DB;
  $file_tbl = MYSQL_FILE_TBL;
  foreach($batches as $batch){
    $files = File::findBySql("SELECT * FROM {$file_db}.`{$file_tbl}` WHERE id IN (
      SELECT fid FROM {$log_db}.email_outbox_attachment WHERE ebatch = '{$database->escapeValue($batch)}'
    )");
    if( $files ){
      foreach($files as $file){
        $attachments[$batch][] = [
          "remoteName" => $file->nice_name . '.' . Generic::fileExt($file->fullPath()),
          "filePath" => $file->fullPath()
        ];
      }
    }
  }

  // build email
  $mgClient = Mailgun::create($mailgun_api_key);

  foreach($mails as $eml){
    $msg_r = [
      'from' => $eml->sender,
      'to' => $eml->receiver,
      'subject' => $eml->subject,
      'text' => $eml->msg_text,
      'html' => $eml->msg_html
    ];
    if( !empty($eml->cc) ) $msg_r['cc'] = \str_replace(';',',',$eml->cc);
    if( !empty($eml->bcc) ) $msg_r['bcc'] = \str_replace(';',',',$eml->bcc);
    if (!empty($eml->headers)) {
      foreach (\explode("|;",$eml->headers) as $header) {
        $header_r = \explode("|:",$header);
        $msg_r[$header_r[0]] = $header_r[1];
      }
    }
    try {
      $result = !empty($attachments[$eml->batch])
        ? $mgClient->messages()->send($mailgun_api_domain,$msg_r,['attachment'=>$attachments[$batch]])
        : $mgClient->messages()->send($mailgun_api_domain,$msg_r);
      if(
        \is_object($result) &&
        !empty($result->getId()) &&
        \strpos($result->getId(), $mailgun_api_domain) !== false
      ){
        $ids[$eml->id] = $result->getId();
      }else{
        $send_errors[] = "[{$eml->id}] Sending failed: \\Unknown Mailgun error.";
      }
    } catch (\Exception $e) {
      $send_errors[] = "[{$eml->id}] Sending failed: {$e->getMessage()}";
    }
  }
  if( !empty($ids) ){
    $update = "UPDATE {$log_db}.email_outbox
               SET status = 'S', qid =
                CASE ";
    foreach($ids as $id=>$qid){
      $update .= " WHEN id = {$id} THEN '{$database->escapeValue($qid)}' ";
    }
    $update .= " END WHERE id IN('".\implode("','",\array_keys($ids))."')";
    if(!$database->query($update)){
      $errs = (new InstanceError($database,true))->get("", true);
      if (!empty($errs)) {
        $send_errors[] = "Record update failed for IDs: [" . \implode(", ", $ids) ."]";
        foreach ($errs as $err) {
          $send_errors[] = $err;
        }
      }
    }
  }
  // End dev process
  $GLOBALS["database"]->closeConnection();
  $GLOBALS["database"] = new \TymFrontiers\MySQLDatabase(MYSQL_SERVER, MYSQL_GUEST_USERNAME, MYSQL_GUEST_PASS);

  if (!empty($send_errors)) {
    echo \json_encode([
      "status" => "4." . \count($send_errors),
      "message" => "Request inconclusive",
      "errors" => $send_errors
    ]);
    exit;
  }
  echo \json_encode([
    "status" => "0.0",
    "message" => \count($ids) . " Email(s) sent successfully",
    "errors" => [],
    "id" => $ids
  ]);
  exit;
else:
  echo \json_encode([
    "status" => "0.2",
    "message" => "No Email(s) found for sending",
    "errors" => [],
    "id" => []
  ]);
  exit;
endif;
