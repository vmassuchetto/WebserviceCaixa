<?php

$dias_de_prazo_para_pagamento = floor((strtotime($ws->GetDataVencimento()) - time()) / 60 * 60 * 24);
$taxa_boleto = 0;
$data_venc = date('d/m/Y', strtotime($ws->GetDataVencimento()));

$nn = $ws->GetNossoNumero();

$dadosboleto["nosso_numero_const1"] = substr($nn, 0, 1);
$dadosboleto["nosso_numero_const2"] = substr($nn, 1, 1);
$dadosboleto["nosso_numero1"] = substr($nn, 2, 3);
$dadosboleto["nosso_numero2"] = substr($nn, 5, 3);
$dadosboleto["nosso_numero3"] = substr($nn, 8, 9);

$valor_cobrado = $ws->GetValor();
$valor_boleto = number_format($valor_cobrado+$taxa_boleto, 2, ',', '');
$dadosboleto["numero_documento"] = $ws->GetNumeroDocumento();
$dadosboleto["data_vencimento"] = date('d/m/Y', strtotime($ws->GetDataVencimento()));
$dadosboleto["data_documento"] = date('d/m/Y', strtotime($ws->GetDataEmissao()));
$dadosboleto["data_processamento"] = date('d/m/Y', strtotime($ws->GetDataEmissao()));
$dadosboleto["valor_boleto"] = $valor_boleto;

$dadosboleto["sacado"] = $ws->GetPagadorNome();
$dadosboleto["endereco1"] = $ws->GetPagadorLogradouro() . ' - ' . $ws->GetPagadorBairro();
$dadosboleto["endereco2"] = $ws->GetPagadorCidade() . ' - ' . $ws->GetPagadorUf() . ' CEP: ' . $ws->GetPagadorCep();

$dadosboleto["demonstrativo1"] = $ws->GetMensagem1();
$dadosboleto["demonstrativo2"] = $ws->GetMensagem2();
$dadosboleto["demonstrativo3"] = '';

$dadosboleto["instrucoes1"] = $ws->GetMensagem1();
$dadosboleto["instrucoes2"] = $ws->GetMensagem2();
$dadosboleto["instrucoes3"] = '';
$dadosboleto["instrucoes4"] = '';

$dadosboleto["quantidade"] = "";
$dadosboleto["valor_unitario"] = "";
$dadosboleto["aceite"] = $ws->GetFlagAceite();
$dadosboleto["especie"] = "R$";
$dadosboleto["especie_doc"] = "";

$dadosboleto["agencia"] = $ws->GetUnidade();
$dadosboleto["conta"] = $ws->GetCodigoBeneficiario();
$dadosboleto["conta_dv"] = '0';

$dadosboleto["conta_cedente"] = $ws->GetCodigoBeneficiario();
$dadosboleto["carteira"] = 'RG';

$cnpj = $ws->GetCnpj();
$dadosboleto["identificacao"] = $ws->GetIdentificacao();
$dadosboleto["cpf_cnpj"] = substr($cnpj, 1, 2) . '.' . substr($cnpj, 3, 3) . '.' . substr($cnpj, 6, 3) . '/' . substr($cnpj, 9, 4) . '-' . substr($cnpj, 13, 2);
$dadosboleto["endereco"] = $ws->GetEndereco1();
$dadosboleto["cidade_uf"] = $ws->GetEndereco2();
$dadosboleto["cedente"] = $ws->GetCedente();