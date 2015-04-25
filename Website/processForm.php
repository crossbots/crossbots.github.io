<?php

$para= "tauamarinho@hotmail.com";
$assunto= "Contato pelo site";
$nome= $_REQUEST['nome'];
$fone= $_PREQUEST['telefone'];
$email= $_REQUEST['email'];
$msg= $_REQUEST['assunto'];

$corpo = "<strong> Mensagem de contato </strong><br><br>";
$corpo .= "<strong> Nome: </strong> $nome";
$corpo .= "<br><strong> telefone: </strong>" . $fone;
$corpo .= "<br><strong> Email: </strong>" . $email;
$corpo .= "<br><strong> Mensagem: </strong>" . $msg;

	$header .= "Content-Type: Texte/html; charset= utf-8\n";
	$header = "From: $email Reply-to: $email\n";
	

@mail($para, $assunto, $corpo, $header);

header("location:contato.html?msg=enviado");


?>