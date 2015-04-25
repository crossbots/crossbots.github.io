<?php

include_once "templates/base.php";

session_start();

require_once realpath(dirname(__FILE__) . '/../src/Google/autoload.php');

$client_id = '206852684132-oh01arhhmvo8bgvag9695u1d90cnospj.apps.googleusercontent.com';
$client_secret = 'H8YFVQ1Om-_v7JTRcpo9TcIT';
$redirect_uri = 'http://minedosbrothers.servegame.com:8081/Dropbox/DropServer/sites/crossbots_novo/site/envia.php';

$client = new Google_Client();
$client->setClientId($client_id);
$client->setAccessType("offline");
$client->setClientSecret($client_secret);
$client->setRedirectUri($redirect_uri);  //url que vai apos fazer a autenticacao
$client->addScope("https://mail.google.com/"); //funcoes que voce declara que vai acessar

if (isset($_REQUEST['logout'])) {
    unset($_SESSION['access_token']);
}

if (isset($_GET['code'])) {
    $client->authenticate($_GET['code']);
    $access_token = $client->getAccessToken();
    $tokens_decoded = json_decode($access_token);
    $refreshToken = $tokens_decoded->refresh_token;
    $redirect = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];
    header('Location: ' . filter_var($redirect, FILTER_SANITIZE_URL));
}

if (isset($_SESSION['access_token']) && $_SESSION['access_token']) {
    $client->setAccessToken($_SESSION['access_token']);
} else {
    $authUrl = $client->createAuthUrl();
}

if ($client->isAccessTokenExpired()) {
    $client->refreshToken("1/y7G_RFERSvoNrO9P_rSBzk6bb91q8rzPmPe1foQDdaQMEudVrK5jSpoR30zcRFq6");
    //$authUrl = $client->createAuthUrl();
    //header('Location: ' . filter_var($authUrl, FILTER_SANITIZE_URL));
}

function formataTexto($string){
    $sanitizedData = strtr($string,'-_', '+/');
    $decodedMessage = base64_decode($sanitizedData);
    return $decodedMessage;
}

function lerEmailById($client, $messageId){
    $service = new Google_Service_Gmail($client);
    $retornoTemporario = array(
            'header' => array(),
            'body',
        );

    $optParamsGet = [];
    $optParamsGet['format'] = 'full'; // Display message in payload
    $message = $service->users_messages->get('me',$messageId,$optParamsGet);
    
    $messagePayload = $message->getPayload();
    $headers = $messagePayload->getHeaders();
    $parts = $messagePayload->getParts();

    foreach ($headers as $header){
        $retornoTemporario['header'][$header->name] = $header->value;
    }   

    $retornoTemporario['body'] = formataTexto($parts[1]['body']->data);
    return $retornoTemporario;
}

function marcaComoLida($client, $messageId){
    $service = new Google_Service_Gmail($client);
    
    $mod = new Google_Service_Gmail_ModifyMessageRequest();
    $mod->setRemoveLabelIds(array("UNREAD"));
    
    $service->users_messages->modify('me',$messageId,$mod);
}

function lerEmail($client, $tag){
    $service = new Google_Service_Gmail($client);
    $optParams = [];
    $optParams['labelIds'] = $tag;
    $messages = $service->users_messages->listUsersMessages('me', $optParams);
    $list = $messages->getMessages();
    
    $retorno = array();
    
    foreach ($list as $list){
        $messageId = $list->getId();             
        $retorno[$messageId] = lerEmailById($client, $messageId);
        //marcaComoLida($client, $messageId);
    }    
    return $retorno;
}

function enviarEmail($client, $texto){
    $service = new Google_Service_Gmail($client);   
    
    $strMailContent = $texto["message"];
    $strMailTextVersion = strip_tags($strMailContent, '');

    $strRawMessage = "";
    $boundary = uniqid(rand(), true);
    $subjectCharset = $charset = 'utf-8';
    $strToMail = $texto["to"];
    $strSesFromEmail = 'samuel.zaduski@gmail.com';
    $strSubject = $texto["subject"]; // . date('M d, Y h:i:s A');

    $strRawMessage .= 'To: ' . " <" . $strToMail . ">" . "\r\n";
    $strRawMessage .= 'From: ' . $texto["nomeRemetente"] . "<" . $strSesFromEmail . ">" . "\r\n";

    $strRawMessage .= 'Subject: =?' . $subjectCharset . '?B?' . base64_encode($strSubject) . "?=\r\n";
    $strRawMessage .= 'MIME-Version: 1.0' . "\r\n";
    $strRawMessage .= 'Content-type: Multipart/Alternative; boundary="' . $boundary . '"' . "\r\n";

    /*
    $filePath = '/LICENSE.txt';
    $finfo = finfo_open(FILEINFO_MIME_TYPE); // return mime type ala mimetype extension
    $mimeType = finfo_file($finfo, $filePath);
    $fileName = 'LICENSE.txt';
    $fileData = base64_encode(file_get_contents($filePath));
     */

    $strRawMessage .= "\r\n--{$boundary}\r\n";
    //$strRawMessage .= 'Content-Type: '. $mimeType .'; name="'. $fileName .'";' . "\r\n";            
    $strRawMessage .= 'Content-ID: <' . $strSesFromEmail . '>' . "\r\n";            
    //$strRawMessage .= 'Content-Description: ' . $fileName . ';' . "\r\n";
    //$strRawMessage .= 'Content-Disposition: attachment; filename="' . $fileName . '"; size=' . filesize($filePath). ';' . "\r\n";
    $strRawMessage .= 'Content-Transfer-Encoding: base64' . "\r\n\r\n";
    //$strRawMessage .= chunk_split(base64_encode(file_get_contents($filePath)), 76, "\n") . "\r\n";
    $strRawMessage .= '--' . $boundary . "\r\n";

    $strRawMessage .= "\r\n--{$boundary}\r\n";
    $strRawMessage .= 'Content-Type: text/plain; charset=' . $charset . "\r\n";
    $strRawMessage .= 'Content-Transfer-Encoding: 7bit' . "\r\n\r\n";
    $strRawMessage .= $strMailTextVersion . "\r\n";

    $strRawMessage .= "--{$boundary}\r\n";
    $strRawMessage .= 'Content-Type: text/html; charset=' . $charset . "\r\n";
    $strRawMessage .= 'Content-Transfer-Encoding: quoted-printable' . "\r\n\r\n";
    $strRawMessage .= $strMailContent . "\r\n"; 
    
    //$mime = strtr(base64_encode($texto["message"]), '+/', '-_');
    $utils = new Google_Utils();
    $mime = $utils->urlSafeB64Encode($strRawMessage);
    
    $msg = new Google_Service_Gmail_Message();
    $msg->setRaw($mime);
    $service->users_messages->send("samuel.zaduski@gmail.com", $msg);
}

$dadosEmail = array(
    "message" =>  " Email do contato: " . trim($_POST['email']) . "<br/><br/>". $_POST['mensagem'],
    "nomeRemetente" => $_POST['nome'],
    "to" => "crossbots@gmail.com",
    "subject" => $_POST['assunto'],
        );

enviarEmail($client, $dadosEmail);
//echo "<pre>"; print_r(lerEmail($client, 'UNREAD'));

unset($_SESSION['access_token']);

$redirect = 'http://crossbots.zapto.org/';
header('Location: ' . filter_var($redirect, FILTER_SANITIZE_URL));

die();