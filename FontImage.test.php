<?php
	require_once('FontImage.class.php');
	
	$fontImage = FontImageFactory::Get();
	$fontImage->SetFont('DIRTF__.TTF');
	$fontImage->SetFontSize(40);
	$fontImage->SetColour(FontImageColour::BLUE);
	
	$fontImage->Generate('THIS IS MY TEST STRING' . rand());
?>