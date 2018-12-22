<?php

include('WebserviceCaixa.php');

// Inicie com as informações do emissor

$emissor = array(
    'CNPJ' => '12302309234123',
    'CODIGO_BENEFICIARIO' => '951955',
	'IDENTIFICACAO' => 'IDENTIFICACAO DO BENEFICIARIO NO BOLETO',
	'ENDERECO1' => 'PRIMEIRA LINHA DO ENDERECO',
	'ENDERECO2' => 'SEGUNDA LINHA DO ENDERECO',
	'UNIDADE' => '99912' // agência de relacionamento
);

// Exemplo de inclusão de boleto

$ws = new WebserviceCaixa($emissor);

$novo_boleto = array(
    // Informações do boleto
    'NOSSO_NUMERO' => '1947658325871322',
    'NUMERO_DOCUMENTO' => '674389152',
    'DATA_EMISSAO' => date('Y-m-d'),
    'DATA_VENCIMENTO' => date('Y-m-d', strtotime('+30 days')),
    'NUMERO_DIAS' =>  '30',
    'VALOR' => '81.53',
    'FLAG_ACEITE' => 'N',

    // Informações do pagador
    'PAGADOR' => array(
        'CPF' => '99999999999', // ou CNPJ
        'NOME' => 'NOME',
        'ENDERECO' => array(
            'LOGRADOURO' => 'LOGRADOURO',
            'BAIRRO' => 'BAIRRO',
            'CIDADE' => 'CIDADE',
            'UF' => 'UF',
            'CEP' => '99999999'
        )
    ),

    // Informações adicionais impressas no boleto e no sistema do beneficiário.
    // Pode-se informar até 4 vezes.
    'FICHA_COMPENSACAO' => array(
        'MENSAGENS' => array(
            'MENSAGEM1' => 'PRIMEIRA LINHA DA MENSAGEM PERSONALIZADA',
            'MENSAGEM2' => 'SEGUNDA LINHA DA MENSAGEM PERSONALIZADA'
        )
    )
);

$ws->Inclui($novo_boleto);

if ($ws->GetCodigoRetorno() == "0") {
    echo "Boleto disponível em " . $ws->GetUrlBoleto() . "\n";
} else {
    echo "Erro ao gerar boleto." . $ws->GetMensagemRetorno() . "\n";
}

print_r($ws->GetExcecao());

// Exemplo de consulta de boleto

$ws = new WebserviceCaixa($emissor);

$consulta_boleto = array(
    'NOSSO_NUMERO' => '1947658325871322',
    'NUMERO_DOCUMENTO' => '674389152'
);
$ws->Consulta($consulta_boleto);

if ($ws->GetCodigoRetorno() == "0") {
    echo "Data de emissão: " . $ws->GetDataEmissao() . "\n";
    echo "Data de vencimento: " . $ws->GetDataVencimento() . "\n";
    echo "Valor: " . $ws->GetValor() . "\n";
} else {
    echo "Erro ao consultar o boleto: " . $ws->GetMensagemRetorno() . "\n";
}

// libera o tratador de erros interno
unset($ws);