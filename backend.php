<?php
// Conexão com o banco de dados
$host = 'localhost';
$dbname = 'fortec23_pastel';
$user = 'fortec23_pastel';
$password = 'gabitassimini';
$conn = new PDO("mysql:host=$host;dbname=$dbname", $user, $password);

// Receber os dados e a ação desejada
$data = json_decode(file_get_contents('php://input'), true);
$acao = $data['acao'] ?? '';

// Função para inserir local
function inserirLocal($local, $latitude, $longitude) {
    global $conn;
    $sql = "INSERT INTO Locais (local, latitude, longitude) VALUES (:local, :latitude, :longitude)";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['local' => $local, 'latitude' => (float)$latitude, 'longitude' => (float)$longitude]);
    echo "Local '$local' inserido com sucesso!";
}

// Função para adicionar ingrediente
function adicionarIngrediente($nomeColuna) {
    global $conn;
    $sql = "ALTER TABLE Receitas ADD COLUMN `$nomeColuna` DOUBLE DEFAULT NULL";
    $stmt = $conn->prepare($sql);
    try {
        $stmt->execute();
        echo "Ingrediente '$nomeColuna' adicionado com sucesso.";
    } catch (PDOException $e) {
        echo "Erro ao adicionar ingrediente: " . $e->getMessage();
    }
}

// Função para inserir receita
function inserirReceita($nomeItem, $ingredientes) {
    global $conn;

    // Buscar os nomes das colunas na tabela Receitas
    $sql = "SHOW COLUMNS FROM Receitas";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

    // Filtrar para obter apenas as colunas de ingredientes
    $ingredientColumns = array_filter($columns, function ($coluna) {
        return $coluna !== 'nome_item';
    });

    // Inicializar as listas para a instrução SQL
    $colunas = ['nome_item'];
    $valores = [':nomeItem'];
    $data = [':nomeItem' => $nomeItem];

    // Mapear os ingredientes para nomes de parâmetros simplificados
    foreach ($ingredientColumns as $indice => $coluna) {
        $parametro = ":param_$indice";
        if (isset($ingredientes[$coluna])) {
            $colunas[] = "`$coluna`";
            $valores[] = $parametro;
            $data[$parametro] = $ingredientes[$coluna];
        }
    }

    // Construir a instrução SQL
    $sql = "INSERT INTO Receitas (" . implode(', ', $colunas) . ") VALUES (" . implode(', ', $valores) . ")";
    $stmt = $conn->prepare($sql);

    // Executar a instrução
    try {
        $stmt->execute($data);
        echo "Receita '$nomeItem' inserida com sucesso!";
    } catch (PDOException $e) {
        echo "Erro ao inserir receita: " . $e->getMessage() . "\nConsulta SQL: " . $sql . "\nDados: " . json_encode($data);
    }
}


// Função para inserir compra
function inserirCompra($nomeItem, $preco, $quantidade, $latitude, $longitude) {
    global $conn;
    $sql = "INSERT INTO Compras (nome_item, preco, quantidade, latitude, longitude) VALUES (:nomeItem, :preco, :quantidade, :latitude, :longitude)";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['nomeItem' => $nomeItem, 'preco' => $preco, 'quantidade' => $quantidade, 'latitude' => (float)$latitude, 'longitude' => (float)$longitude]);
    echo "Compra de '$nomeItem' inserida com sucesso!";
}

// Função para inserir venda
function inserirVenda($nomeItem, $quantidade, $latitude, $longitude) {
    global $conn;
    $sql = "INSERT INTO Vendas (nome_item, quantidade, latitude, longitude) VALUES (:nomeItem, :quantidade, :latitude, :longitude)";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['nomeItem' => $nomeItem, 'quantidade' => $quantidade, 'latitude' => $latitude, 'longitude' => $longitude]);
    echo "Venda de '$nomeItem' inserida com sucesso!";
}

// Função para inserir producao
function inserirProducao($nomeItem, $quantidade) {
    global $conn;
    $sql = "INSERT INTO Producao (nome_item, quantidade) VALUES (:nomeItem, :quantidade)";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['nomeItem' => $nomeItem, 'quantidade' => $quantidade]);
    echo "Producao de '$nomeItem' inserida com sucesso!";
}

// Função para ler os ingredientes (nomes das colunas da tabela Receitas, exceto nome_item)
function lerIngredientes() {
    global $conn;
    $sql = "SHOW COLUMNS FROM Receitas";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    $semni = array_filter($columns, function ($coluna) {
        return $coluna !== 'nome_item';
    });
    return array_filter($semni, function ($coluna) {
        return $coluna !== 'time_stamp';
    });
}

// Função para ler os itens (nomes dos itens na coluna nome_item na tabela Receitas)
function lerItens() {
    global $conn;
    $sql = "SELECT nome_item FROM Receitas";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
}

// Função para ler os itens (nomes dos itens na coluna nome_item na tabela Receitas)
function lerAdicionais() {
    global $conn;
    $sql = "SELECT adicional FROM Adicionais";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
}

// Função para ler os itens (nomes dos itens na coluna nome_item na tabela Receitas)
function lerRevenda() {
    global $conn;
    $colunasNulas = [];

    // Obter os nomes das colunas
    $stmtColunas = $conn->query("SHOW COLUMNS FROM Receitas");
    $colunas = $stmtColunas->fetchAll(PDO::FETCH_COLUMN);

    // Verificar cada coluna individualmente para encontrar as completamente nulas
    foreach ($colunas as $coluna) {
        // Criar consulta para verificar se a coluna é completamente NULL
        $sql = "SELECT COUNT(*) AS total
                FROM Receitas
                WHERE `".$coluna."` IS NOT NULL";

        // Preparar e executar a consulta
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Se não houver linhas com valor na coluna, ela está completamente NULL
        if ((int)$result['total'] === 0) {
            $colunasNulas[] = $coluna;
        }
    }

    return array_unique($colunasNulas);
}

function lerVIngredientes() {
    global $conn;
    $colunasNaoNulas = [];
    
    // Obter os nomes das colunas
    $stmtColunas = $conn->query("SHOW COLUMNS FROM Receitas");
    $colunas = $stmtColunas->fetchAll(PDO::FETCH_COLUMN);

    // Verificar cada coluna individualmente para encontrar as que não estão completamente nulas
    foreach ($colunas as $coluna) {

        // Consulta SQL para verificar se a coluna não é completamente NULL
        $sql = "SELECT COUNT(*) AS total
                FROM Receitas
                WHERE `".$coluna."` IS NOT NULL";

        // Preparar e executar a consulta
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Se a coluna tiver pelo menos uma linha com valor, ela não está completamente nula
        if ((int)$result['total'] > 0) {
            $colunasNaoNulas[] = $coluna;
        }
    }
    
    $semni = array_filter(array_unique($colunasNaoNulas), function ($coluna) {
        return $coluna !== 'nome_item';
    });
    return array_filter($semni, function ($coluna) {
        return $coluna !== 'time_stamp';
    });
}

// Função para inserir preço
function inserirPreco($item, $local, $preco) {
    global $conn;
    $sql = "INSERT INTO Precos (nome_item, local, preco) VALUES (:item, :local, :preco)";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['item' => $item, 'local' => $local, 'preco' => $preco]);
    echo "Preço do item '$item' no local '$local' inserido com sucesso!";
}

// Função para inserir preço
function inserirAdicional($adicional, $item, $local) {
    global $conn;
    $sql = "INSERT INTO Adicionais (adicional, nome_item, local) VALUES (:adicional, :item, :local)";
    $stmt = $conn->prepare($sql);
    $stmt->execute(['adicional' => $adicional, 'item' => $item, 'local' => $local]);
    echo "Adicional '$adicional' do item '$item' no local '$local' inserido com sucesso!";
}

// Função para ler os locais (nomes dos locais na coluna 'local' na tabela Locais)
function lerLocais() {
    global $conn;
    $sql = "SELECT local FROM Locais";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
}

function calcularProducaoTotal($ingrediente, $inicio, $fim) {
    global $conn;

    // Certificar-se de que as datas estão no formato correto com hora e minuto
    $inicio = date('Y-m-d', strtotime($inicio)) . ' 00:00:00';
    $fim = date('Y-m-d', strtotime($fim)) . ' 23:59:59';
    
    // Obter todos os nome_item e valores não nulos da coluna ingrediente na tabela Receitas
    $sqlReceitas = "SELECT nome_item, `$ingrediente` AS ingrediente_valor
                    FROM Receitas
                    WHERE `$ingrediente` IS NOT NULL";

    $stmtReceitas = $conn->prepare($sqlReceitas);
    $stmtReceitas->execute();
    $receitas = $stmtReceitas->fetchAll(PDO::FETCH_ASSOC);

    // Inicializar o total de produção
    $producaoTotal = 0;

    // Calcular a produção para cada nome_item
    foreach ($receitas as $receita) {
        $nomeItem = $receita['nome_item'];
        $ingredienteValor = (float)$receita['ingrediente_valor'];

        // Obter a soma das quantidades não negativas na tabela Producao
        $sqlProducao = "SELECT SUM(quantidade) AS soma_quantidade
                        FROM Producao
                        WHERE nome_item = :nome_item
                        AND quantidade >= 0
                        AND time_stamp 
                        BETWEEN :inicio AND :fim";

        $stmtProducao = $conn->prepare($sqlProducao);
        $stmtProducao->execute([
            ':nome_item' => $nomeItem,
            ':inicio' => $inicio,
            ':fim' => $fim]);
        $producao = $stmtProducao->fetch(PDO::FETCH_ASSOC);

        $somaQuantidade = (float)$producao['soma_quantidade'];

        // Multiplicar a soma das quantidades pelo valor do ingrediente
        $producaoTotal += $somaQuantidade * $ingredienteValor;
    }

    // Retornar o valor total da produção
    return $producaoTotal;
}

function pegaLocal($latitude, $longitude) {
    global $conn;

    // Consulta SQL para encontrar o local mais próximo
    $sql = "SELECT local,
                   latitude,
                   longitude,
                   SQRT(POW((latitude - :latitude), 2) + POW((longitude - :longitude), 2)) AS distancia
            FROM Locais
            ORDER BY distancia ASC
            LIMIT 1";

    // Preparar e executar a consulta com os parâmetros fornecidos
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':latitude' => $latitude,
        ':longitude' => $longitude
    ]);

    // Obter o local mais próximo ou retornar um valor padrão
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['local'] ?? null;
}


function calcularVendasAdicionais($ingrediente_adicional, $inicio, $fim) {
    global $conn;
    $total = 0;

    // Certificar-se de que as datas estão no formato correto com hora e minuto
    $inicio = date('Y-m-d', strtotime($inicio)) . ' 00:00:00';
    $fim = date('Y-m-d', strtotime($fim)) . ' 23:59:59';

    // Obter todas as vendas que podem ser correlacionadas na tabela Adicional
    $sqlVendas = "SELECT nome_item, latitude, longitude, quantidade
                  FROM Vendas
                  WHERE time_stamp 
                  BETWEEN :inicio AND :fim";

    $stmtVendas = $conn->prepare($sqlVendas);
    $stmtVendas->execute([
            ':inicio' => $inicio,
            ':fim' => $fim
        ]);
    $vendas = $stmtVendas->fetchAll(PDO::FETCH_ASSOC);

    foreach ($vendas as $venda) {
        $nome_item = $venda['nome_item'];
        $latitude = $venda['latitude'];
        $longitude = $venda['longitude'];
        $quantidade = (int)$venda['quantidade'];

        // Obter o nome do local usando as coordenadas
        $local = pegaLocal($latitude, $longitude);

        if ($local) {
            // Verificar se o item e local correspondem na tabela Adicional ao ingrediente adicional
            $sqlAdicional = "SELECT 1
                             FROM Adicional
                             WHERE nome_item = :nome_item
                             AND local = :local
                             AND adicional = :ingrediente_adicional
                             LIMIT 1";

            $stmtAdicional = $conn->prepare($sqlAdicional);
            $stmtAdicional->execute([
                ':nome_item' => $nome_item,
                ':local' => $local,
                ':ingrediente_adicional' => $ingrediente_adicional
            ]);

            // Se houver correspondência, some a quantidade
            if ($stmtAdicional->fetch()) {
                $total += $quantidade;
            }
        }
    }

    return $total;
}

function calcularVendas($inicio, $fim) {
    global $conn;
    $total = 0;

    // Certificar-se de que as datas estão no formato correto com hora e minuto
    $inicio = date('Y-m-d', strtotime($inicio)) . ' 00:00:00';
    $fim = date('Y-m-d', strtotime($fim)) . ' 23:59:59';
    
    // Obter todas as vendas que podem ser correlacionadas na tabela Adicional
    $sqlVendas = "SELECT nome_item, latitude, longitude, quantidade
                  FROM Vendas
                  WHERE time_stamp 
                  BETWEEN :inicio AND :fim";

       // Preparar e executar a consulta com tratamento de erro
    try {
        $stmtVendas = $conn->prepare($sqlVendas);
        $stmtVendas->execute([
            ':inicio' => $inicio,
            ':fim' => $fim
        ]);
    } catch (PDOException $e) {
        // Exibir uma mensagem de erro específica
        echo "Erro ao executar a consulta: " . $e->getMessage();
        return 0;
    }
    $vendas = $stmtVendas->fetchAll(PDO::FETCH_ASSOC);

    foreach ($vendas as $venda) { //para cada venda
        $nome_item = $venda['nome_item'];
        $latitude = $venda['latitude'];
        $longitude = $venda['longitude'];
        $quantidade = (int)$venda['quantidade'];

        // Obter o nome do local usando as coordenadas
        $local = pegaLocal($latitude, $longitude);

        if ($local) {
            // Verificar se o item e local correspondem na tabela Adicional ao ingrediente adicional
            $sqlPrecos= "SELECT preco
                         FROM Precos
                         WHERE nome_item = :nome_item
                         AND local = :local
                         LIMIT 1";

            $stmtAdicional = $conn->prepare($sqlAdicional);
            $stmtAdicional->execute([
                ':nome_item' => $nome_item,
                ':local' => $local,
            ]);
            $precoResult = $stmtAdicional->fetch();
            // Se houver correspondência, some a quantidade
            if ($precoResult) {
                $preco = (float)$precoResult['preco'];
                $total += $preco * $quantidade;
            }
        }
    }

    return $total;
}

function consultarTabela($tabela, $coluna="quantidade", $item, $inicio, $fim){
    global $conn;

    // Certificar-se de que as datas estão no formato correto com hora e minuto
    $inicio = date('Y-m-d', strtotime($inicio)) . ' 00:00:00';
    $fim = date('Y-m-d', strtotime($fim)) . ' 23:59:59';
    
    $sql = "SELECT SUM(".$coluna.") AS total_quantidade
            FROM ".$tabela."
            WHERE time_stamp BETWEEN :inicio AND :fim";
    // Adicionar condição de nome_item apenas se não for 'ANY'
    if ($item !== 'ANY') {
        $sql .= " AND nome_item = :nome_item";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':nome_item' => $item,
        ':inicio' => $inicio,
        ':fim' => $fim
    ]);
    } else {
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':inicio' => $inicio,
        ':fim' => $fim
    ]);
        
    }
    $compras = $stmt->fetch(PDO::FETCH_ASSOC);
    return (float)($compras['total_quantidade'] ?? 0);
}


// Função para ler os locais (nomes dos locais na coluna 'local' na tabela Locais)
function consultarIngredientes($item, $inicio, $fim) {
    return consultarTabela('Compras','quantidade', $item, $inicio, $fim) - calcularProducaoTotal($item, $inicio, $fim);
}

// Função para ler os locais (nomes dos locais na coluna 'local' na tabela Locais)
function consultarProdutos($item, $inicio, $fim) {
    return consultarTabela('Producao','quantidade', $item, $inicio, $fim) - consultarTabela('Vendas','quantidade', $item, $inicio, $fim);
}

// Função para ler os locais (nomes dos locais na coluna 'local' na tabela Locais)
function consultarItensProntos($item, $inicio, $fim) {
    return consultarTabela('Compras','quantidade', $item, $inicio, $fim) - consultarTabela('Vendas','quantidade', $item, $inicio, $fim);
}

// Função para ler os locais (nomes dos locais na coluna 'local' na tabela Locais)
function consultarAdicionais($item, $inicio, $fim) {
    return consultarTabela('Compras','quantidade', $item, $inicio, $fim) - calcularVendasAdicionais($item, $inicio, $fim);
}

function consultarLucro($inicio, $fim){
    return calcularVendas($inicio, $fim)-consultarTabela('Compras', 'preco', 'ANY', $inicio, $fim);
}

function obterTodasLinhas($tabela) {
    global $conn;

    // Lista de tabelas permitidas para evitar SQL injection
    $tabelasPermitidas = ['Receitas', 'Compras', 'Vendas', 'Locais', 'Precos', 'Producao', 'Adicionais'];
    if (!in_array($tabela, $tabelasPermitidas, true)) {
        die("Tabela não permitida.");
    }

    // Montar a consulta para selecionar todas as linhas
    $sql = "SELECT * FROM $tabela";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Verificar a ação recebida e chamar a função correspondente
switch ($acao) {
    case 'inserirPreco':
        inserirPreco($data['item'], $data['local'], $data['preco']);
        break;
    case 'inserirAdicional':
        inserirAdicional($data['adicional'], $data['item'], $data['local']);
        break;
    case 'lerLocais':
        header('Content-Type: application/json');
        echo json_encode(array_values(lerLocais()));
        break;
    case 'inserirLocal':
        inserirLocal($data['local'], $data['latitude'], $data['longitude']);
        break;
    case 'adicionarIngrediente':
        adicionarIngrediente($data['nomeIngrediente']);
        break;
    case 'inserirReceita':
        inserirReceita($data['nomeReceita'], $data['ingredientes']);
        break;
    case 'inserirCompra':
        inserirCompra($data['ingrediente'], $data['preco'], $data['quantidade'], $data['latitude'], $data['longitude']);
        break;
    case 'inserirVenda':
        inserirVenda($data['item'], $data['quantidade'], $data['latitude'], $data['longitude']);
        break;
    case 'inserirProducao':
        inserirProducao($data['item'], $data['quantidade']);
        break;
    case 'lerIngredientes':
        header('Content-Type: application/json');
        echo json_encode(array_values(lerIngredientes()));
        break;
    case 'lerItens':
        header('Content-Type: application/json');
        echo json_encode(array_values(lerItens()));
        break;
    case 'lerAdicionais':
        header('Content-Type: application/json');
        echo json_encode(array_values(lerAdicionais()));
        break;
    case 'lerRevenda':
        header('Content-Type: application/json');
        echo json_encode(array_values(lerRevenda()));
        break;
    case 'lerIngrediente':
        header('Content-Type: application/json');
        echo json_encode(array_values(lerVIngredientes()));
        break;
    case 'consultarIngredientes':
        header('Content-Type: application/json');
        echo consultarIngredientes($data['item'], $data['inicio'], $data['fim']);
        break;
    case 'consultarReceitas':
        header('Content-Type: application/json');
        echo consultarProdutos($data['item'], $data['inicio'], $data['fim']);
        break;
    case 'consultarRevenda':
        header('Content-Type: application/json');
        echo consultarItensProntos($data['item'], $data['inicio'], $data['fim']);
        break;
    case 'consultarAdicionais':
        header('Content-Type: application/json');
        echo consultarAdicionais($data['item'], $data['inicio'], $data['fim']);
        break;
    case 'consultarLucro':
        header('Content-Type: application/json');
        echo consultarLucro($data['inicio'], $data['fim']);
        break;
    case 'obterTodasLinhas':
        header('Content-Type: application/json');
        $tabela = $data['tabela'];
        $resultado = obterTodasLinhas($tabela);
        echo json_encode($resultado);
        break;
    default:
        echo "Ação inválida!";
        break;
}
