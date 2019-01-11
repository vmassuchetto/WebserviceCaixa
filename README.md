# Cobrança registrada da Caixa Econômica Federal

Acesso às operações básicas de consulta, inclusão e alteração
de cobranças registradas no Webservice da Caixa Econômica Federal.

## Modo de uso

```php
include('WebserviceCaixa.php');
$ws = new WebserviceCaixa($parametros_do_emissor);
$ws->Inclui($parametros_de_inclusao);
echo $ws->GetUrlBoleto();
```

Verifique o [arquivo de exemplo](Exemplo.php) para ver como construir
os parâmetros especificados no [manual de uso da CEF](http://www.caixa.gov.br/Downloads/cobranca-caixa/Manual_Leiaute_Webservice.pdf).

### BoletoPHP

Para utilizar o [BoletoPHP](https://github.com/CobreGratis/boletophp), baixe
os arquivos necessários:

```sh
mkdir -p phpboleto/include
curl -s https://raw.githubusercontent.com/CobreGratis/boletophp/master/boleto_cef.php -o phpboleto/boleto_cef.php
curl -s https://raw.githubusercontent.com/CobreGratis/boletophp/master/include/funcoes_cef.php -o phpboleto/include/funcoes_cef.php
```

E no código chame o método `$ws->GeraBoletoPHP()`:

```php
include('WebserviceCaixa.php');
$ws = new WebserviceCaixa($parametros_do_emissor);
$ws->Inclui($parametros_de_inclusao);
$ws->GeraBoletoPHP(); // exibe boleto na tela
```

## Configuração

Para sobrescrever as configurações padrões, crie um arquivo de configuração:

```sh
cp ConfigPadrao.php Config.php
```

Para colocar em produção, desabilite o modo de desenvolvimento para enviar
os atributos corretos ao serviço da Caixa:

```php
define('DESENVOLVIMENTO', false);
```

## Executar com Docker

```sh
docker run -it --rm --name WebserviceCaixa -v "$PWD":/usr/src/myapp -w /usr/src/myapp php:5-alpine php Exemplo.php
```

## Depuração

```php
$ws = new WebserviceCaixa($parametros_do_emissor);

// realize a operação

print_r($ws->GetMensagemRetorno()); // mensagem de retorno
print_r($ws->GetExcecao());         // exceção
print_r($ws->consulta);             // consulta realizada
print_r($ws->resposta);             // resposta obtida
print_r($ws->nusoap);               // objeto NuSOAP
```

Verifique também se [alguém já teve seu problema antes](https://github.com/vmassuchetto/WebserviceCaixa/issues?q=is%3Aissue+is%3Aclosed).

### Códigos de erro comuns

Dentre as saídas possíveis para `$ws->GetMensagemRetorno()`:

(54) OPERACAO NAO PERMITIDA - HASH DIVERGENTE: Há um problema com os
campos que geram o campo `HASH_AUTENTICACAO`. Confirme no manual se os
valores informados para `CODIGO_BENEFICIARIO`, `NOSSO_NUMERO`,
`DATA_VENCIMENTO`, `VALOR` e `CNPJ` são válidos e possuem o tamanho correto.

(X5) USUARIO NAO AUTORIZADO A EXECUTAR A TRANSACAO: Ocorre ao informar
um `CODIGO_BENEFICIARIO` inválido. Confirme com o HelpDesk da Caixa se o
código utilizado está devidamente liberado para o serviço.

(X5) TRANSAÇÃO TEMPORARIAMENTE INDISPONÍVEL: Pode ocorrer sem aviso
prévio e retornar à normalidade após algum tempo. Indica que o sistema da
Caixa está provavelmente indisponível.

### Entendendo as mensagens de exceção

Organizando a saída de `print_r($ws->GetExcecao())`, tem-se algo parecido com:

```xml
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">
[...]
<soapenv:Body>
[..]
<DADOS>
<EXCECAO>
    EXCECAO NO BAR_MANUTENCAO_COBRANCA_BANCARIA_WS.SOAPInput_Empresas_Externas.
    DETALHES:
    ParserException(1) - Funcao: ImbDataFlowNode::createExceptionList,
    Texto Excecao: Node throwing exception, Texto de Insercao
    (1) - BAR_MANUTENCAO_COBRANCA_BANCARIA_WS.SOAPInput_Empresas_Externas.ParserException
    (2) - Funcao: ImbSOAPInputNode::validateData, Texto Excecao: Error occurred in ImbSOAPInputHelper::validateSOAPInput(), Texto de Insercao(1) - BAR_MANUTENCAO_COBRANCA_BANCARIA_WS.SOAPInput_Empresas_Externas.ParserException
    (3) - Funcao: ImbRootParser::parseNextItem, Texto Excecao: Exception whilst parsing.ParserException
    (4) - Funcao: ImbSOAPParser::createSoapShapedTree, Texto Excecao: problem creating SOAP tree from bitstream.ParserException
    (5) - Funcao: ImbXMLNSCParser::parseLastChild, Texto Excecao: XML Parsing Errors have occurred.ParserException
    (6) - Funcao: ImbXMLNSCDocHandler::handleParseErrors, Texto Excecao: A schema validation error has occurred while parsing the XML document, Texto de Insercao
        (1) - 6012, Texto de Insercao
        (2) - 1, Texto de Insercao
        (3) - 28, Texto de Insercao
        (4) - 43, Texto de Insercao
aqui --->     (5) - cvc-enumeration-valid: The value "ISENTO" is not valid with respect to the enumeration facet for type "#Anonymous". It must be a value from the enumeration., Texto de Insercao
        (6) - /XMLNSC/{http://schemas.xmlsoap.org/soap/envelope/}:Envelope/{http://schemas.xmlsoap.org/soap/envelope/}:Body/{http://caixa.gov.br/sibar/manutencao_cobranca_bancaria/boleto/externo}:SERVICO_ENTRADA/DADOS/INCLUI_BOLETO/TITULO/JUROS_MORA/TIPO.
        [...]
```

A parte relevante geralmente fica no final da pilha de rastreamento sinalizada
pelas sequências `(1) ... (2) ... (3) ...`

Neste exemplo, o valor informado para o campo `ISENTO` é inválido dentre
os valores especificados no manual:

    The value "ISENTO" is not valid with respect to the enumeration facet for type "#Anonymous".
    It must be a value from the enumeration.

Outros casos como campos chave não preenchidos, caracteres especiais e
tipos inválidos são reportados nesta estrutura.
