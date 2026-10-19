<?php
/**
 * HTML <head> + opening body markup.
 * Expects an optional $pageTitle variable to be set before include.
 */
$pageTitle = $pageTitle ?? 'Hospital Management System';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= esc($pageTitle) ?> | HMS</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/5.3.3/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
<link href="/hms/assets/css/style.css" rel="stylesheet">
</head>
<body>
