<?php
namespace TymFrontiers;
require_once "../.appinit.php";
use \TymFrontiers\HTTP\Header;

\header("Content-Type: application/json");
$post = \json_decode( \file_get_contents('php://input'), true); // json data
$post = !empty($post) ? $post : (
  !empty($_POST) ? $_POST : $_GET
);

$gen = new Generic;
$auth = new API\Authentication ($api_sign_patterns);
$http_auth = $auth->validApp ();
if ( !$http_auth && ( empty($post['form']) || empty($post['CSRFToken']) ) ){
  HTTP\Header::unauthorized (false,'', Generic::authErrors ($auth,"Request [Auth-App]: Authetication failed.",'self',true));
}

$rqp = [
  "type" =>["type","option", ["country","city","state","lga"]],
  "code" => ["code","username", 2, 12, [], "UPPER"],
  "countryCode" => ["countryCode","username", 2, 2, [], "UPPER"],
  "stateCode" => ["stateCode","username", 5, 12, [], "UPPER"],
  "search" => ["search","text",3,25],
  "page" => ["page","int"],
  "limit" => ["limit","int"],

  "form" => ["form","text",2,72],
  "CSRFToken" => ["CSRFToken","text",5,1024]
];
$req = ["type"];
if (!$http_auth) {
  $req[] = 'form';
  $req[] = 'CSRFToken';
}
if (@ $post['type'] == "state") $req[] = "countryCode";
if (@ \in_array($post['type'], ["city","lga"])) $req[] = "stateCode";
$params = $gen->requestParam($rqp,$post,$req);
if (!$params || !empty($gen->errors)) {
  $errors = (new InstanceError($gen, false))->get("requestParam",true);
  echo \json_encode([
    "status" => "3." . \count($errors),
    "errors" => $errors,
    "message" => "Request halted"
  ]);
  exit;
}
if( !$http_auth ){
  if ( !$gen->checkCSRF($params["form"],$params["CSRFToken"]) ) {
    $errors = (new InstanceError($gen, false))->get("checkCSRF",true);
    echo \json_encode([
      "status" => "3." . \count($errors),
      "errors" => $errors,
      "message" => "Request halted."
    ]);
    exit;
  }
}
// Begin process
$count = 0;
$data = new MultiForm(MYSQL_DATA_DB, $params["type"],'code');
$data->current_page = $page = (int)$params['page'] > 0 ? (int)$params['page'] : 1;
$query = "SELECT typ.code, typ.name ";
if ($params['type'] == "country") {
  $query .= ", tdnc.dvalue AS numberCode, tdpc.dvalue AS phoneCode, tdiso.dvalue AS iso3";
} if ($params['type'] == "state") {
  $query .= ", typ.country_code AS countryCode ";
} if (\in_array($params['type'], ["city", "lga"])) {
  $query .= ", typ.state_code AS stateCode ";
}
$query .= " FROM :db:.:tbl: AS typ ";
$join = "";
if ($params['type'] == "country") {
  $join .= "LEFT JOIN :db:.country_data AS tdnc ON tdnc.country_code=typ.code AND tdnc.dkey = 'NUMBERCODE'
            LEFT JOIN :db:.country_data AS tdpc ON tdpc.country_code=typ.code AND tdpc.dkey = 'PHONECODE'
            LEFT JOIN :db:.country_data AS tdiso ON tdiso.country_code=typ.code AND tdiso.dkey = 'ISO3' ";
}
$cond = " WHERE 1=1 ";
$params['type'] = $database->escapeValue(\strtolower($params['type']));
$params['search'] = $database->escapeValue(\strtolower($params['search']));
$params['code'] = $database->escapeValue($params['code']);
if (!empty($params['code'])) {
  $cond .= " AND typ.`code` = '{$params['code']}' ";
}
if ( $params['type'] == 'state') {
  $cond .= " AND typ.country_code = '{$database->escapeValue($params['countryCode'])}'";
} else if ( \in_array($params['type'],['city','lga']) ){
  $cond .= " AND typ.state_code = '{$params['stateCode']}'";
} else {
}
$count = $data->findBySql("SELECT COUNT(*) AS cnt FROM :db:.:tbl: AS typ {$cond} ");
$count = $data->total_count = $count ? $count[0]->cnt : 0;

$data->per_page = $limit = !empty($params['code']) ? 1 : (
    (int)$params['limit'] > 0 ? (int)$params['limit'] : 500
  );
$query .= $join;
$query .= $cond;

$query .= " ORDER BY  LOWER(typ.`name`) = '-other', typ.`name` ASC";
$query .= " LIMIT {$data->per_page} ";
$query .= " OFFSET {$data->offset()}";
$found = $data->findBySql($query);
if( !$found ){
  die( \json_encode([
    "message" => "No data found.",
    "errors" => [],
    "status" => "0.2"
    ]) );
}
$result = [
  'records' => (int)$count,
  'page'  => $data->current_page,
  'pages' => $data->totalPages(),
  'limit' => $limit,
  'hasPreviousPage' => $data->hasPreviousPage(),
  'hasNextPage' => $data->hasNextPage(),
  'previousPage' => $data->hasPreviousPage() ? $data->previousPage() : 0,
  'nextPage' => $data->hasNextPage() ? $data->nextPage() : 0
];
foreach($found as $k=>$obj){
  unset($found[$k]->errors);
  unset($found[$k]->current_page);
  unset($found[$k]->per_page);
  unset($found[$k]->total_count);
  unset($found[$k]->country_code);
  unset($found[$k]->state_code);
}
$result["message"] = "Request completed.";
$result["errors"] = [];
$result["status"] = "0.0";
$result["results"] = $found;

echo \json_encode($result);
exit;
// End dev process
