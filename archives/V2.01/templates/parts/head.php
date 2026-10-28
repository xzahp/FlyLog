<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?= fly_tpl_seo($meta ?? array()) ?>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🚁</text></svg>">
<link rel="stylesheet" href="<?= fly_e(fly_base_url() . 'assets/app.css') ?>">
<?php fly_do_action('fly_head'); ?>
</head>
<body>
