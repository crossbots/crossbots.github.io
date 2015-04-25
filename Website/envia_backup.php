<?php

$emaildestinatario = "samuel.zaduski@gmail.com";
 
/* Verifica qual � o sistema operacional do servidor para ajustar o cabe�alho de forma correta. N�o alterar */
if(PHP_OS == "Linux") $quebra_linha = "\n"; //Se for Linux
elseif(PHP_OS == "WINNT") $quebra_linha = "\r\n"; // Se for Windows
else die("Este script nao esta preparado para funcionar com o sistema operacional de seu servidor");
 
// Passando os dados obtidos pelo formul�rio para as vari�veis abaixo
$nomeremetente     = $_POST['nome'];
$emailremetente    = trim($_POST['email']);
$emaildestinatario = trim($emaildestinatario);
$assunto           = $_POST['assunto'];
$mensagemHTML      = $_POST['mensagem'];
 
 
/* Montando o cabe�alho da mensagem */
$headers = "MIME-Version: 1.1".$quebra_linha;
$headers .= "Content-type: text/html; charset=iso-8859-1".$quebra_linha;
// Perceba que a linha acima cont�m "text/html", sem essa linha, a mensagem n�o chegar� formatada.
$headers .= "From: ".$emailremetente.$quebra_linha;
$headers .= "Return-Path: ".$emailremetente.$quebra_linha;
$headers .= "Reply-To: ".$emailremetente.$quebra_linha;
// Note que o e-mail do remetente ser� usado no campo Reply-To (Responder Para)
 
/* Enviando a mensagem */
if(mail($emaildestinatario, $assunto, $mensagemHTML, $headers, "-r". $emailremetente)){
 
/* Mostrando na tela as informa��es enviadas por e-mail */
    print "Mensagem <b>$assunto</b> enviada com sucesso!<br><br>
    De: $emairemetente<br>
    Para: $emaildestinatario<br>
    <p><a href='".$_SERVER["HTTP_REFERER"]."'>Voltar</a></p>";
} else {
print "erro";
}
?>