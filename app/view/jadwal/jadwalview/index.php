<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Jadwal Kuliah</title>
    <link rel="stylesheet" href="app/view/jadwal/jadwalview/style.css">
    <script>
        (function applyThemeAccent() {
            const key = 'si-jadwal-accent-color';
            const saved = localStorage.getItem(key);
            if (saved) {
                document.documentElement.style.setProperty('--theme-accent', saved);
            }
        })();
    </script>
</head>
<body>

<h1>Jadwal Hari <?php echo $currentDay; ?></h1>

<div class="nav-buttons">
    <a href="?day=<?php echo $prevDay; ?>" class="btn">← Previous</a>
    <a href="?day=<?php echo $nextDay; ?>" class="btn">Next →</a>
</div>

<div class="table-wrapper">
    <table>
        <thead>
            <tr>
                <?php foreach ($header as $col): ?>
                    <?php if ($col != 'Hari'): ?>
                        <th><?php echo htmlspecialchars($col); ?></th>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($jadwal[$currentDay] as $row): ?>
                <tr>
                    <?php foreach ($header as $col): ?>
                        <?php if ($col != 'Hari'): ?>
                            <td><?php echo htmlspecialchars($row[$col] ?? ''); ?></td>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>


</body>
</html>
