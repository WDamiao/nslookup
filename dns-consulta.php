<?php
// ========================
// Script de Consulta DNS
// ========================
// Este script realiza consultas DNS em vários tipos de registros (A, MX, NS, SOA, TXT, CNAME, SRV)
// para um domínio principal e diversos subdomínios comuns.
// Também destaca servidores autorizados de e-mail e DNS, e imprime os dados brutos no console JS.
// ========================

// Função para sanitizar domínio (remove protocolos, espaços e barras)
function sanitize_domain($input) {
    // Remove espaços no início/fim e transforma em minúsculas
    $domain = trim(strtolower($input));

    // Remove protocolos (http, https, ftp, etc.)
    $domain = preg_replace('#^https?://#', '', $domain);
    $domain = preg_replace('#^ftp://#', '', $domain);

    // Remove qualquer '/' no final ou no caminho
    $domain = preg_replace('#/.*$#', '', $domain);

    // Remove espaços adicionais em qualquer lugar
    $domain = str_replace(' ', '', $domain);

    return $domain;
}

// Entrada do domínio via POST, com sanitização
$rawDomain = $_POST['domain'] ?? '';
$domain = sanitize_domain($rawDomain);

if (empty($domain)) {
    echo "<p>Domínio não informado ou inválido.</p>";
    exit;
}

// Inicializa variáveis
$allRecords = [];
$rawRecords = [];
$wwwCnameRecord = null;
$wwwDomain = 'www.' . $domain;

// Tipos e subdomínios a serem consultados
$types = [DNS_A, DNS_MX, DNS_NS, DNS_SOA, DNS_SRV];
$subdomains = ['pop', 'imap', 'smtp', 'mail', 'webmail', 'autodiscover', 'www', '_dmarc', 'email-locaweb'];

// ------------------------------
// Captura CNAME de www.dominio
// ------------------------------
$wwwCname = dns_get_record($wwwDomain, DNS_CNAME);
if ($wwwCname) {
    foreach ($wwwCname as $record) {
        $wwwCnameRecord = [
            'host' => $record['host'],
            'type' => 'CNAME',
            'info' => $record['target']
        ];
        $rawRecords[] = $record;
    }
}

// ------------------------------------------
// Consulta registros principais (A, MX, NS...)
// ------------------------------------------
foreach ($types as $type) {
    $records = dns_get_record($domain, $type);
    if ($records) {
        foreach ($records as $record) {
            $allRecords[] = [
                'host' => $record['host'],
                'type' => get_dns_type_name($type),
                'info' => get_record_info($type, $record)
            ];
            $rawRecords[] = $record;
        }
    }
}

// ---------------------------------------------
// Consulta registros CNAME e TXT de subdomínios
// ---------------------------------------------
foreach ($subdomains as $subdomain) {
    $subdomainDomain = $subdomain . '.' . $domain;

    // Busca CNAME
    $records = dns_get_record($subdomainDomain, DNS_CNAME);
    if ($records) {
        foreach ($records as $record) {
            $allRecords[] = [
                'host' => $record['host'],
                'type' => 'CNAME',
                'info' => $record['target']
            ];
            $rawRecords[] = $record;
        }
    }

    // Se não for CNAME, busca TXT
    if (!$records) {
        $recordsTXT = dns_get_record($subdomainDomain, DNS_TXT);
        if ($recordsTXT) {
            foreach ($recordsTXT as $record) {
                $allRecords[] = [
                    'host' => $record['host'],
                    'type' => 'TXT',
                    'info' => implode(", ", $record['entries'])
                ];
                $rawRecords[] = $record;
            }
        }
    }
}

// --------------------------------------------
// Consulta SRV específico para autodiscover
// --------------------------------------------
$srvDomain = "_autodiscover._tcp." . $domain;
$srvRecords = dns_get_record($srvDomain, DNS_SRV);
if ($srvRecords) {
    foreach ($srvRecords as $record) {
        $allRecords[] = [
            'host' => $record['host'],
            'type' => 'SRV',
            'info' => "{$record['pri']} {$record['weight']} {$record['port']} {$record['target']}"
        ];
        $rawRecords[] = $record;
    }
}

// -----------------------------------
// Consulta registros TXT do domínio
// -----------------------------------
$recordsTXT = dns_get_record($domain, DNS_TXT);
if ($recordsTXT) {
    foreach ($recordsTXT as $record) {
        $allRecords[] = [
            'host' => $record['host'],
            'type' => 'TXT',
            'info' => implode(", ", $record['entries'])
        ];
        $rawRecords[] = $record;
    }
}

// -------------------------------------------
// Exibe os registros DNS encontrados em HTML
// -------------------------------------------
if (!empty($allRecords)) {
    echo "<h2>Registros DNS para: " . htmlspecialchars($domain) . "</h2>";
    echo "<table>";
    echo "<tr><th>Host</th><th>Tipo</th><th>Informações</th></tr>";

    // Exibe CNAME do www primeiro, se existir
    if ($wwwCnameRecord) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($wwwCnameRecord['host']) . "</td>";
        echo "<td>" . htmlspecialchars($wwwCnameRecord['type']) . "</td>";
        echo "<td>" . htmlspecialchars($wwwCnameRecord['info']) . "</td>";
        echo "</tr>";
    }

    // Lista de MX e NS autorizados para destaque
    $mxPermitidos = ['mx.a.locaweb.com.br', 'mx.b.locaweb.com.br', 'mx.core.locaweb.com.br', 'mx.jk.locaweb.com.br'];
    $nsPermitidos = ['ns1.locaweb.com.br', 'ns2.locaweb.com.br', 'ns3.locaweb.com.br'];

    // Exibe os demais registros
    foreach ($allRecords as $record) {
        // Evita duplicar CNAME do www já exibido
        if ($wwwCnameRecord && $record['type'] === 'CNAME' && $record['host'] === $wwwDomain) {
            continue;
        }

        $classe = '';
        if ($record['type'] === 'MX' && in_array(strtolower($record['info']), $mxPermitidos)) {
            $classe = 'highlight-green';
        }
        if ($record['type'] === 'NS' && in_array(strtolower($record['info']), $nsPermitidos)) {
            $classe = 'highlight-green';
        }

        echo "<tr>";
        echo "<td>" . htmlspecialchars($record['host']) . "</td>";
        echo "<td>" . htmlspecialchars($record['type']) . "</td>";
        echo "<td class='$classe'>" . htmlspecialchars($record['info']) . "</td>";
        echo "</tr>";
    }

    echo "</table>";
} else {
    echo "<p>Nenhum registro encontrado para o domínio " . htmlspecialchars($domain) . ".</p>";
}

// --------------------------
// Envia dados brutos ao console JS
// --------------------------
echo "<script>";
echo "const rawData = " . json_encode($rawRecords, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . ";";
echo "console.log('Dados brutos da consulta DNS:', rawData);";
echo "</script>";

// =======================
// Funções Auxiliares
// =======================

/**
 * Converte constante DNS_* para nome textual
 */
function get_dns_type_name($type) {
    return match($type) {
        DNS_A => 'A',
        DNS_MX => 'MX',
        DNS_NS => 'NS',
        DNS_TXT => 'TXT',
        DNS_CNAME => 'CNAME',
        DNS_SOA => 'SOA',
        DNS_SRV => 'SRV',
        default => 'Desconhecido'
    };
}

/**
 * Retorna a informação relevante do registro, dependendo do tipo
 */
function get_record_info($type, $record) {
    return match($type) {
        DNS_A => "{$record['ip']} (" . gethostbyaddr($record['ip']) . ")",
        DNS_CNAME => $record['target'],
        DNS_TXT => implode(", ", $record['entries']),
        DNS_NS, DNS_MX => $record['target'],
        DNS_SOA => "{$record['mname']} {$record['rname']} {$record['serial']}",
        DNS_SRV => "{$record['pri']} {$record['weight']} {$record['port']} {$record['target']}",
        default => ''
    };
}
?>

