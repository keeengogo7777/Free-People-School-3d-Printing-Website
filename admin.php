<?php
session_start();
$config = require __DIR__ . '/config.php';
require __DIR__ . '/db.php';

date_default_timezone_set('Europe/Kyiv');

$isLoggedIn = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
$errors = [];
$successMessage = null;
$previousInput = [
    'entry_date' => date('Y-m-d'),
    'bottles_collected' => '',
    'revenue' => '',
    'parts_printed' => '',
    'notes' => '',
];

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$isLoggedIn && isset($_POST['password'])) {
        if (hash_equals($config['admin_password'], $_POST['password'])) {
            $_SESSION['is_admin'] = true;
            $isLoggedIn = true;
        } else {
            $errors[] = 'Невірний пароль. Спробуйте ще раз або зверніться до координатора проєкту.';
        }
    } elseif ($isLoggedIn && isset($_POST['entry_date'], $_POST['bottles_collected'], $_POST['revenue'], $_POST['parts_printed'])) {
        $entryDate = $_POST['entry_date'];
        $previousInput = array_merge($previousInput, [
            'entry_date' => $entryDate,
            'bottles_collected' => $_POST['bottles_collected'],
            'revenue' => $_POST['revenue'],
            'parts_printed' => $_POST['parts_printed'],
            'notes' => $_POST['notes'] ?? '',
        ]);
        $dateValid = DateTime::createFromFormat('Y-m-d', $entryDate) !== false;
        $bottles = filter_var($_POST['bottles_collected'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        $revenue = filter_var($_POST['revenue'], FILTER_VALIDATE_FLOAT);
        if ($revenue !== false) {
            $revenue = round($revenue, 2);
        }
        $parts = filter_var($_POST['parts_printed'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        $notes = trim($_POST['notes'] ?? '');

        if (!$dateValid || $bottles === false || $revenue === false || $parts === false) {
            $errors[] = 'Будь ласка, переконайтеся, що всі поля заповнені правильно.';
        } else {
            try {
                $statement = $pdo->prepare('INSERT INTO production_logs (entry_date, bottles_collected, revenue, parts_printed, notes) VALUES (:entry_date, :bottles, :revenue, :parts, :notes)');
                $statement->execute([
                    ':entry_date' => $entryDate,
                    ':bottles' => $bottles,
                    ':revenue' => $revenue,
                    ':parts' => $parts,
                    ':notes' => $notes !== '' ? $notes : null,
                ]);
                $successMessage = 'Запис додано! Дякуємо за вашу працю.';
                $previousInput = [
                    'entry_date' => date('Y-m-d'),
                    'bottles_collected' => '',
                    'revenue' => '',
                    'parts_printed' => '',
                    'notes' => '',
                ];
            } catch (PDOException $e) {
                $errors[] = 'Не вдалося зберегти запис. Перевірте введені дані та спробуйте ще раз.';
            }
        }
    }
}

$recentEntriesStmt = $pdo->query('SELECT entry_date, bottles_collected, revenue, parts_printed, notes, created_at FROM production_logs ORDER BY entry_date DESC, id DESC LIMIT 20');
$recentEntries = $recentEntriesStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Панель волонтера – Free People School</title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css" />
</head>
<body class="admin-body">
    <div class="admin-container">
        <header class="admin-header">
            <h1>Панель волонтера</h1>
            <p>Тут ви можете вносити щоденні результати збору вторсировини та друку деталей.</p>
            <a class="button button--secondary" href="index.php">На головну</a>
        </header>

        <?php if (!$isLoggedIn): ?>
            <section class="card">
                <h2>Вхід для волонтерів</h2>
                <p>Введіть пароль, який ви отримали від координатора проєкту.</p>
                <?php if ($errors): ?>
                    <div class="alert alert--error">
                        <?php foreach ($errors as $error): ?>
                            <p><?php echo htmlspecialchars($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <form method="post" class="form">
                    <label for="password">Пароль</label>
                    <input type="password" name="password" id="password" required />
                    <button type="submit" class="button">Увійти</button>
                </form>
            </section>
        <?php else: ?>
            <section class="card">
                <div class="card__header">
                    <h2>Додати новий запис</h2>
                    <a class="link" href="?logout=1">Вийти</a>
                </div>
                <p>Заповніть форму нижче, щоб оновити статистику на головній сторінці.</p>
                <?php if ($successMessage): ?>
                    <div class="alert alert--success">
                        <p><?php echo htmlspecialchars($successMessage); ?></p>
                    </div>
                <?php endif; ?>
                <?php if ($errors): ?>
                    <div class="alert alert--error">
                        <?php foreach ($errors as $error): ?>
                            <p><?php echo htmlspecialchars($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <form method="post" class="form form--grid">
                    <div class="form__group">
                        <label for="entry_date">Дата</label>
                        <input type="date" name="entry_date" id="entry_date" value="<?php echo htmlspecialchars($previousInput['entry_date']); ?>" required />
                    </div>
                    <div class="form__group">
                        <label for="bottles_collected">Здано пляшок</label>
                        <input type="number" name="bottles_collected" id="bottles_collected" min="0" step="1" value="<?php echo htmlspecialchars($previousInput['bottles_collected']); ?>" required />
                    </div>
                    <div class="form__group">
                        <label for="revenue">Отримано коштів (грн)</label>
                        <input type="number" name="revenue" id="revenue" min="0" step="0.01" value="<?php echo htmlspecialchars($previousInput['revenue']); ?>" required />
                    </div>
                    <div class="form__group">
                        <label for="parts_printed">Роздруковано деталей</label>
                        <input type="number" name="parts_printed" id="parts_printed" min="0" step="1" value="<?php echo htmlspecialchars($previousInput['parts_printed']); ?>" required />
                    </div>
                    <div class="form__group form__group--full">
                        <label for="notes">Коментар (необовʼязково)</label>
                        <textarea name="notes" id="notes" rows="3" placeholder="Наприклад: надруковано кріплення для авто, друкували клас 10-А"><?php echo htmlspecialchars($previousInput['notes']); ?></textarea>
                    </div>
                    <div class="form__actions form__group--full">
                        <button type="submit" class="button">Зберегти</button>
                    </div>
                </form>
            </section>

            <section class="card">
                <h2>Останні внесені дані</h2>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Дата</th>
                                <th>Пляшки</th>
                                <th>Сума, грн</th>
                                <th>Деталі</th>
                                <th>Коментар</th>
                                <th>Час внесення</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$recentEntries): ?>
                                <tr>
                                    <td colspan="6">Ще немає записів. Додайте перший прямо зараз!</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentEntries as $entry): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars(date('d.m.Y', strtotime($entry['entry_date']))); ?></td>
                                        <td><?php echo (int) $entry['bottles_collected']; ?></td>
                                        <td><?php echo number_format($entry['revenue'], 2, ',', ' '); ?></td>
                                        <td><?php echo (int) $entry['parts_printed']; ?></td>
                                        <td><?php echo htmlspecialchars($entry['notes'] ?? '—'); ?></td>
                                        <td><?php echo htmlspecialchars(date('d.m.Y H:i', strtotime($entry['created_at']))); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>
    </div>
</body>
</html>
