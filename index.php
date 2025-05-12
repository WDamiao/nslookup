<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consulta DNS</title>
    <link rel="stylesheet" href="assets/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@300;400;700&display=swap" rel="stylesheet">
</head>
<body>
    <header>
        <h1>Consulta de Registros DNS</h1>
    </header>

    <main>
        <form method="POST">
            <div class="form-group">
                <input type="text" id="domain" name="domain" placeholder="Digite o domínio" required>
                <button type="submit">Consultar</button>
            </div>
        </form>

        <div class="table-container">
            <?php
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['domain'])) {
                    include 'dns-consulta.php';
                }
            ?>
        </div>
    </main>
</body>
</html>

