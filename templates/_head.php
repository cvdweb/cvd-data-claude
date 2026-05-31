<!DOCTYPE html>
<html lang="vi" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="<?= e(SCHOOL_NAME) ?> — Hệ thống quản lý dữ liệu giáo viên">

  <title><?= e($pageTitle ?? 'EduVN') ?> — <?= e(SCHOOL_SHORT) ?></title>

  <!-- Favicon -->
  <link rel="icon" type="image/png" href="/assets/favicon.png">

  <!-- Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@300;400;500;600;700;800;900&display=swap">

  <!-- Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

  <!-- App styles -->
  <link rel="stylesheet" href="/assets/style.css">

  <!-- Màu chủ đạo theo trường (override CSS variable) -->
  <?php if (PRIMARY_COLOR !== '#1a6ef5'): ?>
  <style>
    :root {
      --primary: <?= PRIMARY_COLOR ?>;
      --primary-dark: color-mix(in srgb, <?= PRIMARY_COLOR ?> 80%, black);
      --primary-light: color-mix(in srgb, <?= PRIMARY_COLOR ?> 12%, white);
      --primary-glow: color-mix(in srgb, <?= PRIMARY_COLOR ?> 20%, transparent);
      --shadow-primary: 0 8px 24px color-mix(in srgb, <?= PRIMARY_COLOR ?> 30%, transparent);
    }
  </style>
  <?php endif; ?>

  <!-- Extra CSS từng trang (nếu có) -->
  <?php if (!empty($extraStyles)): ?>
    <?php foreach ($extraStyles as $style): ?>
      <link rel="stylesheet" href="<?= e($style) ?>">
    <?php endforeach; ?>
  <?php endif; ?>

  <!-- Inline style từng trang (nếu có) -->
  <?php if (!empty($inlineStyle)): ?>
    <style><?= $inlineStyle ?></style>
  <?php endif; ?>
</head>
<body>
<div class="app-layout">
