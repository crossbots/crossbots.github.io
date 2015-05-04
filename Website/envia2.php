<?php

include_once "templates/base.php";

session_start();

require_once realpath(dirname(__FILE__) . '/../src/Google/autoload.php');
$file = "gmail.json";

$arrayJson = json_decode(file_get_contents($file));
 
$client_id = $arrayJson->web->client_id;
$client_secret = $arrayJson->web->client_secret;
$redirect_uri = $arrayJson->web->redirect_uris[0];

$client = new Google_Client();
$client->setClientId($client_id);
$client->setAccessType("offline");
$client->setClientSecret($client_secret);
$client->setRedirectUri($redirect_uri);  //url que vai apos fazer a autenticacao
$client->addScope("https://mail.google.com/"); //funcoes que voce declara que vai acessar

if (isset($_GET['code'])) {
    $client->authenticate($_GET['code']);
    $access_token = $client->getAccessToken();
    $tokens_decoded = json_decode($access_token);
    $refreshToken = $tokens_decoded->refresh_token;
    $client->getRefreshToken();
    if($refreshToken){
        escreveJson($file, '"web":{','"refreshToken":"'. $refreshToken . '",');
        unset($_SESSION['access_token']);
        echo ("Refresh token = " . $refreshToken); 
        $botao = '<input type="button" name="leremail" value="Voltar"  onclick="window.open(';
        $botao .= "'contato.html','_parent'";
        $botao .= ')" />';
        echo $botao;
        die("<br>Refresh token salvo com sucesso");
    }    
    $redirect = 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];
    header('Location: ' . filter_var($redirect, FILTER_SANITIZE_URL));
}
 
if (isset($_SESSION['access_token']) && $_SESSION['access_token']) {
    $client->setAccessToken($_SESSION['access_token']);
} else {
    $authUrl = $client->createAuthUrl();
}
 
if ($client->isAccessTokenExpired()) {
    $refresh = $arrayJson->web->refreshToken;
    if ($refresh){
        $client->refreshToken($refresh);
    }
    else {
        if ($client->getRefreshToken()){
            escreveJson($file, '"web":{','"refreshToken":"'. $client->getRefreshToken() . '",');
        }
        else{
            $authUrl = $client->createAuthUrl();
            header('Location: ' . filter_var($authUrl, FILTER_SANITIZE_URL));
        }
    }    
}

function escreveJson($file, $cod, $string){
    $json = file_get_contents($file);
    $replace = str_replace($cod, $cod . $string ,$json);
    $final = fopen($file,'w+');
    fwrite($final, $replace);
    fclose($final);
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
    foreach($parts as $part){
        if ($part->filename != null && strlen($part->filename) > 0) {
            $filename = $part->filename;
            $attId = $part->body->attachmentId;
            $attachPart = $service->users_messages_attachments->get("me", $messageId, $attId);
            $fileByteArray = formataTexto($attachPart->data);
            
            $arquivo = fopen(__DIR__ . "/../../anexos_email/" . $filename,'wrx');
            fwrite($arquivo, $fileByteArray);
            fclose($arquivo);
        }
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
    $strSubject = $texto["subject"]; // . date('M d, Y h:i:s A');
    $strSesFromEmail = $texto["from"];
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

$emailFrom = $arrayJson->web->client_email;
    $dadosEmail = array(
    "message" =>  " Email do contato: " . trim($_POST['email']) . "<br/><br/>". $_POST['mensagem'],
    "from" => $emailFrom,
    "nomeRemetente" => $_POST['nome'],
    "to" => trim($_POST['to']),
    "subject" => $_POST['assunto'],
        );

enviarEmail($client, $dadosEmail);
//echo "<pre>"; print_r(lerEmail($client, 'UNREAD'));

unset($_SESSION['access_token']);

$redirect = 'http://crossbots.zapto.org/';
header('Location: ' . filter_var($redirect, FILTER_SANITIZE_URL));

die();
