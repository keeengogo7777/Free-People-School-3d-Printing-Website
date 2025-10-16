<?php
require __DIR__ . '/db.php';

date_default_timezone_set('Europe/Kyiv');

function fetchAggregatedStats(PDO $pdo, ?string $startDate = null, ?string $endDate = null): array
{
    $query = 'SELECT COALESCE(SUM(bottles_collected), 0) AS bottles, COALESCE(SUM(revenue), 0) AS revenue, COALESCE(SUM(parts_printed), 0) AS parts FROM production_logs';
    $conditions = [];
    $params = [];

    if ($startDate !== null) {
        $conditions[] = 'entry_date >= :start';
        $params[':start'] = $startDate;
    }

    if ($endDate !== null) {
        $conditions[] = 'entry_date <= :end';
        $params[':end'] = $endDate;
    }

    if ($conditions) {
        $query .= ' WHERE ' . implode(' AND ', $conditions);
    }

    $statement = $pdo->prepare($query);
    $statement->execute($params);

    return $statement->fetch() ?: ['bottles' => 0, 'revenue' => 0, 'parts' => 0];
}

$today = date('Y-m-d');
$startOfWeek = date('Y-m-d', strtotime('monday this week'));
$startOfMonth = date('Y-m-01');

$dailyStats = fetchAggregatedStats($pdo, $today, $today);
$weeklyStats = fetchAggregatedStats($pdo, $startOfWeek, $today);
$monthlyStats = fetchAggregatedStats($pdo, $startOfMonth, $today);
$totalStats = fetchAggregatedStats($pdo);

$recentEntriesStmt = $pdo->query('SELECT entry_date, bottles_collected, revenue, parts_printed, notes FROM production_logs ORDER BY entry_date DESC, id DESC LIMIT 10');
$recentEntries = $recentEntriesStmt->fetchAll();

$weeklyTrendStmt = $pdo->query(<<<SQL
    SELECT DATE_FORMAT(entry_date, '%x-%v') AS week_label,
           MIN(entry_date) AS week_start,
           SUM(bottles_collected) AS bottles,
           SUM(revenue) AS revenue,
           SUM(parts_printed) AS parts
    FROM production_logs
    WHERE entry_date >= DATE_SUB(CURDATE(), INTERVAL 11 WEEK)
    GROUP BY week_label
    ORDER BY week_start ASC
SQL);
$weeklyTrend = $weeklyTrendStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Free People School – 3D друк для ЗСУ</title>
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.6/dist/chart.umd.min.js" defer></script>
    <script>window.weeklyTrend = <?php echo json_encode($weeklyTrend, JSON_UNESCAPED_UNICODE); ?>;</script>
    <script src="assets/js/dashboard.js" defer></script>
</head>
<body>
    <header class="hero">
        <div class="hero__overlay"></div>
        <div class="hero__content container">
            <h1>Free People School: 3D-друк для перемоги</h1>
            <p>Разом перетворюємо пластикові пляшки на деталі для українських захисників. Кожна гривня та кожна роздрукована деталь наближає перемогу.</p>
            <a class="button" href="admin.php">Панель волонтера</a>
        </div>
    </header>

    <main class="container">
        <section class="stats-grid">
            <article class="stats-card">
                <h2>Сьогодні</h2>
                <p class="stats-card__metric"><span><?php echo (int) $dailyStats['bottles']; ?></span> пляшок</p>
                <p class="stats-card__metric"><span><?php echo number_format($dailyStats['revenue'], 2, ',', ' '); ?></span> грн</p>
                <p class="stats-card__metric"><span><?php echo (int) $dailyStats['parts']; ?></span> деталей</p>
            </article>
            <article class="stats-card">
                <h2>Цей тиждень</h2>
                <p class="stats-card__metric"><span><?php echo (int) $weeklyStats['bottles']; ?></span> пляшок</p>
                <p class="stats-card__metric"><span><?php echo number_format($weeklyStats['revenue'], 2, ',', ' '); ?></span> грн</p>
                <p class="stats-card__metric"><span><?php echo (int) $weeklyStats['parts']; ?></span> деталей</p>
            </article>
            <article class="stats-card">
                <h2>Цей місяць</h2>
                <p class="stats-card__metric"><span><?php echo (int) $monthlyStats['bottles']; ?></span> пляшок</p>
                <p class="stats-card__metric"><span><?php echo number_format($monthlyStats['revenue'], 2, ',', ' '); ?></span> грн</p>
                <p class="stats-card__metric"><span><?php echo (int) $monthlyStats['parts']; ?></span> деталей</p>
            </article>
            <article class="stats-card">
                <h2>Від початку проєкту</h2>
                <p class="stats-card__metric"><span><?php echo (int) $totalStats['bottles']; ?></span> пляшок</p>
                <p class="stats-card__metric"><span><?php echo number_format($totalStats['revenue'], 2, ',', ' '); ?></span> грн</p>
                <p class="stats-card__metric"><span><?php echo (int) $totalStats['parts']; ?></span> деталей</p>
            </article>
        </section>

        <section class="section">
            <div class="section__header">
                <h2>Динаміка продуктивності</h2>
                <p>Щотижнева статистика переробки та друку за останні три місяці.</p>
            </div>
            <div class="chart-container" role="presentation">
                <canvas id="productivityChart" aria-label="Графік продуктивності" role="img"></canvas>
            </div>
        </section>

        <section class="section">
            <div class="section__header">
                <h2>Останні оновлення</h2>
                <p>Звіт за останні дні від волонтерів принт-лабораторії.</p>
            </div>
            <?php if (empty($recentEntries)): ?>
                <p class="empty-state">Ще немає жодного запису. Додайте першу статистику через панель волонтера.</p>
            <?php else: ?>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Дата</th>
                                <th>Пляшки</th>
                                <th>Сума, грн</th>
                                <th>Деталі</th>
                                <th>Коментар</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentEntries as $entry): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars(date('d.m.Y', strtotime($entry['entry_date']))); ?></td>
                                    <td><?php echo (int) $entry['bottles_collected']; ?></td>
                                    <td><?php echo number_format($entry['revenue'], 2, ',', ' '); ?></td>
                                    <td><?php echo (int) $entry['parts_printed']; ?></td>
                                    <td><?php echo htmlspecialchars($entry['notes'] ?? '—'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <footer class="footer">
        <div class="container footer__content">
            <p>&copy; <?php echo date('Y'); ?> Free People School. Добровільний учнівський проєкт 3D-друку для ЗСУ.</p>
            <p>Разом ми здатні на більше: долучайтеся, поширюйте інформацію, підтримуйте.</p>
        </div>
    </footer>
</body>
</html>
