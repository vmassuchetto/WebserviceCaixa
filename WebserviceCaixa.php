<?php
/**
 * Consulta e registro de boletos da Caixa Econômica Federal
 *
 * Estrutura de diretórios:
 *
 *   /
 *     |-- /lib                   Bibliotecas utilizadas
 *     |-- WebserviceCaixa.php    Biblioteca
 */
$config_file = dirname(__FILE__) . '/Config.php';
if (is_file($config_file)) {
	include($config_file);
} else {
	include(dirname(__FILE__) . '/ConfigPadrao.php');
}

include(dirname(__FILE__) . '/lib/nusoap/lib/nusoap.php');
include(dirname(__FILE__) . '/lib/XmlDomConstruct.php');

class WebserviceCaixa {

	var $args;
	var $consulta;
	var $resposta;
	var $nusoap;

	/**
	 * Construtor atribui e formata parâmetros em $this->args
	 */
	function __construct($args = array()) {
		$this->resposta = array();

		set_error_handler(array($this, 'ErrorHandler'));

		// Localização HTTP dos arquivos WSDL
		// A URL de desenvolvimento parece ter sido desativada pela CEF
		$base_url = 'https://barramento.caixa.gov.br';
		$this->wsdl_consulta = $base_url . '/sibar/ConsultaCobrancaBancaria/Boleto/Externo?wsdl';
		$this->wsdl_manutencao = $base_url . '/sibar/ManutencaoCobrancaBancaria/Boleto/Externo?wsdl';

		$this->args = $this->CleanArray($args);
	}

	function __destruct() {
		restore_error_handler();
	}

	/**
	 * Remove warning específico do Nusoap
	 */
	function ErrorHandler($errno, $errstr, $errfile, $errline) {
		if (!(false !== strpos($errfile, 'lib/nusoap/lib/nusoap.php') && $errline == 4694))
			echo("Warning: " . $errstr . " in " . $errfile . ":" . $errline . "\n");
	}

	/**
	 * Limpa os campos de um array usando `CleanString`
	 */
	 function CleanArray($e) {

		 return is_array($e) ? array_map(array($this, 'CleanArray'), $e) : $this->CleanString($e);
	}

	/**
	 * Formata string de acordo com o requerido pelo webservice
	 *
	 * @see https://stackoverflow.com/a/3373364/513401
	 */
	function CleanString($str) {
		$replaces = array(
			'S'=>'S', 's'=>'s', 'Z'=>'Z', 'z'=>'z', 'À'=>'A', 'Á'=>'A', 'Â'=>'A', 'Ã'=>'A', 'Ä'=>'A', 'Å'=>'A', 'Æ'=>'A', 'Ç'=>'C', 'È'=>'E', 'É'=>'E',
			'Ê'=>'E', 'Ë'=>'E', 'Ì'=>'I', 'Í'=>'I', 'Î'=>'I', 'Ï'=>'I', 'Ñ'=>'N', 'Ò'=>'O', 'Ó'=>'O', 'Ô'=>'O', 'Õ'=>'O', 'Ö'=>'O', 'Ø'=>'O', 'Ù'=>'U',
			'Ú'=>'U', 'Û'=>'U', 'Ü'=>'U', 'Ý'=>'Y', 'Þ'=>'B', 'ß'=>'Ss', 'à'=>'a', 'á'=>'a', 'â'=>'a', 'ã'=>'a', 'ä'=>'a', 'å'=>'a', 'æ'=>'a', 'ç'=>'c',
			'è'=>'e', 'é'=>'e', 'ê'=>'e', 'ë'=>'e', 'ì'=>'i', 'í'=>'i', 'î'=>'i', 'ï'=>'i', 'ð'=>'o', 'ñ'=>'n', 'ò'=>'o', 'ó'=>'o', 'ô'=>'o', 'õ'=>'o',
			'ö'=>'o', 'ø'=>'o', 'ù'=>'u', 'ú'=>'u', 'û'=>'u', 'ý'=>'y', 'þ'=>'b', 'ÿ'=>'y'
		);
		
		return preg_replace('/[^0-9A-Za-z;,.\- ]/', '', strtoupper(strtr(trim($str), $replaces)));
	}

	/**
	 * Adiciona informações para impressão do boleto no objeto
	 */
	function InformacoesBoleto($resposta) {
		$this->resposta = array_merge(
			$this->resposta,
			array(
				'CEDENTE' => $this->args['CODIGO_BENEFICIARIO'],
				'IDENTIFICACAO' => $this->args['IDENTIFICACAO'],
				'ENDERECO1' => $this->args['ENDERECO1'],
				'ENDERECO2' => $this->args['ENDERECO2'],
				'CNPJ' => $this->args['CNPJ'],
				'UNIDADE' => $this->args['UNIDADE'],
				'CODIGO_BENEFICIARIO' => $this->args['CODIGO_BENEFICIARIO']
			)
		);
	}

	/**
	 * Chamada do NuSOAP ao WebService
	 */
	function CallNuSOAP($wsdl, $operacao, $conteudo) {
		$client = new nusoap_client($wsdl, true, $timeout = TIMEOUT);

		$response = $client->call($operacao, $conteudo, RETRIES);
		$err = $client->getError();

		if ($err) {
			print_r($client);
			trigger_error($err);
			$this->nusoap = array(
				'MENSAGEM' => $err,
				'RESPOSTA' => htmlspecialchars($client->response)
			);
		}

		$this->consulta = $client->request;
		$this->resposta = $response;
	}

	/**
	 * Cálculo do Hash de autenticação segundo página 7 do manual.
	 */
	function HashAutenticacao($args) {
		$raw = preg_replace('/[^A-Za-z0-9]/', '',
			'0' . $args['CODIGO_BENEFICIARIO'] .
			$args['NOSSO_NUMERO'] .
			((!$args['DATA_VENCIMENTO']) ?
				sprintf('%08d', 0) :
				strftime('%d%m%Y', strtotime($args['DATA_VENCIMENTO']))) .
			sprintf('%015d', preg_replace('/[^0-9]/', '', $args['VALOR'])) .
			sprintf('%014d', $this->args['CNPJ']));
		return base64_encode(hash('sha256', $raw, true));
	}

	/**
	 * Construção do documento XML para consultas.
	 */
	function ConsultaXML($args) {
		$xml_root = 'consultacobrancabancaria:SERVICO_ENTRADA';
		$xml = new XmlDomConstruct('1.0', 'iso-8859-1');
		$xml->preserveWhiteSpace = !DESENVOLVIMENTO;
		$xml->formatOutput = DESENVOLVIMENTO;
		$xml->fromMixed(array($xml_root => $args));
		$xml_root_item = $xml->getElementsByTagName($xml_root)->item(0);
		$xml_root_item->setAttribute('xmlns:consultacobrancabancaria',
			'http://caixa.gov.br/sibar/consulta_cobranca_bancaria/boleto');
		$xml_root_item->setAttribute('xmlns:sibar_base',
			'http://caixa.gov.br/sibar');

		$xml_string = $xml->saveXML();
		$xml_string = preg_replace('/^<\?.*\?>/', '', $xml_string);
		$xml_string = preg_replace('/<(\/)?MENSAGEM[0-9]>/', '<\1MENSAGEM>', $xml_string);

		return $xml_string;
	}

	/**
	 * Prepara e executa consultas
	 *
	 * Parâmetros mínimos para que o boleto possa ser consultado.
	 */
	function Consulta($args) {
		$args = array_merge($this->args, $args);

		// Para consultas, DATA_VENCIMENTO e VALOR devem ser preenchidos com zeros
		$autenticacao = $this->HashAutenticacao(array_merge($args,
			array(
				'DATA_VENCIMENTO' => 0,
				'VALOR' => 0,
			)
		));

		$xml_array = array(
			'sibar_base:HEADER' => array(
				'VERSAO' => '1.0',
				'AUTENTICACAO' => $autenticacao,
				'USUARIO_SERVICO' => DESENVOLVIMENTO ? 'SGCBS01D' : 'SGCBS02P',
				'OPERACAO' => 'CONSULTA_BOLETO',
				'SISTEMA_ORIGEM' => 'SIGCB',
				'UNIDADE' => $args['UNIDADE'],
				'DATA_HORA' => date('YmdHis'),
			),
			'DADOS' => array(
				'CONSULTA_BOLETO' => array(
					'CODIGO_BENEFICIARIO' => $args['CODIGO_BENEFICIARIO'],
					'NOSSO_NUMERO' => $args['NOSSO_NUMERO'],
				)
			)
		);

		$this->CallNuSOAP($this->wsdl_consulta, 'CONSULTA_BOLETO', $this->ConsultaXml($xml_array));
	}

	/**
	 * Construção do documento XML para operações de manutenção
	 *
	 * Operações de inclusão e alteração
	 */
	function ManutencaoXml($args) {
		$xml_root = 'manutencaocobrancabancaria:SERVICO_ENTRADA';
		$xml = new XmlDomConstruct('1.0', 'iso-8859-1');
		$xml->preserveWhiteSpace = !DESENVOLVIMENTO;
		$xml->formatOutput = DESENVOLVIMENTO;
		$xml->fromMixed(array($xml_root => $args));
		$xml_root_item = $xml->getElementsByTagName($xml_root)->item(0);
		$xml_root_item->setAttribute('xmlns:manutencaocobrancabancaria',
			'http://caixa.gov.br/sibar/manutencao_cobranca_bancaria/boleto/externo');
		$xml_root_item->setAttribute('xmlns:sibar_base',
			'http://caixa.gov.br/sibar');

		$xml_string = $xml->saveXML();
		$xml_string = preg_replace('/^<\?.*\?>/', '', $xml_string);
		$xml_string = preg_replace('/<(\/)?MENSAGEM[0-9]>/', '<\1MENSAGEM>', $xml_string);

		return $xml_string;
	}

	/**
	 * Prepara e executa inclusões e alterações de boleto
	 *
	 * @param str $operacao INCLUI_BOLETO ou ALTERA_BOLETO
	 */
	function Manutencao($xml_array, $operacao) {

		$this->CallNuSOAP($this->wsdl_manutencao, $operacao, $this->ManutencaoXml($xml_array));
	}

	/**
	 * Realiza a operação de inclusão
	 *
	 * Parâmetros mínimos para que o boleto possa ser incluído.
	 */
	function Inclui($args) {
		$args = array_merge($this->args, $args);
		$xml_array = array(
			'sibar_base:HEADER' => array(
				'VERSAO' => '1.0',
				'AUTENTICACAO' => $this->HashAutenticacao($args),
				'USUARIO_SERVICO' => DESENVOLVIMENTO ? 'SGCBS01D' : 'SGCBS02P',
				'OPERACAO' => 'INCLUI_BOLETO',
				'SISTEMA_ORIGEM' => 'SIGCB',
				'UNIDADE' => $args['UNIDADE'],
				'DATA_HORA' => date('YmdHis'),
			),
			'DADOS' => array(
				'INCLUI_BOLETO' => array(
					'CODIGO_BENEFICIARIO' => $args['CODIGO_BENEFICIARIO'],
					'TITULO' => array(
						'NOSSO_NUMERO' => $args['NOSSO_NUMERO'],
						'NUMERO_DOCUMENTO' => $args['NUMERO_DOCUMENTO'],
						'DATA_VENCIMENTO' => $args['DATA_VENCIMENTO'],
						'VALOR' => $args['VALOR'],
						'TIPO_ESPECIE' => '99',
						'FLAG_ACEITE' => $args['FLAG_ACEITE'],
						'DATA_EMISSAO' => $args['DATA_EMISSAO'],
						'JUROS_MORA' => array(
							'TIPO' => 'ISENTO',
							'VALOR' => '0',
						),
						'VALOR_ABATIMENTO' => '0',
						'POS_VENCIMENTO' => array(
							'ACAO' => 'DEVOLVER',
							'NUMERO_DIAS' => $args['NUMERO_DIAS'],
						),
						'CODIGO_MOEDA' => '09',
						'PAGADOR' => $args['PAGADOR'],
						'FICHA_COMPENSACAO' => $args['FICHA_COMPENSACAO']
					)
				)
			)
		);

		return $this->Manutencao($xml_array, 'INCLUI_BOLETO');
	}

	/**
	 * Realiza a operação de alteração
	 *
	 * Parâmetros mínimos para que o boleto possa ser alterado.
	 */
	function Altera($args) {
		$args = array_merge($this->args, $args);
		$xml_array = array(
			'sibar_base:HEADER' => array(
				'VERSAO' => '1.0',
				'AUTENTICACAO' => $this->HashAutenticacao($args),
				'USUARIO_SERVICO' => DESENVOLVIMENTO ? 'SGCBS01D' : 'SGCBS02P',
				'OPERACAO' => 'ALTERA_BOLETO',
				'SISTEMA_ORIGEM' => 'SIGCB',
				'UNIDADE' => $args['UNIDADE'],
				'DATA_HORA' => date('YmdHis'),
			),
			'DADOS' => array(
				'ALTERA_BOLETO' => array(
					'CODIGO_BENEFICIARIO' => $args['CODIGO_BENEFICIARIO'],
					'TITULO' => array(
						'NOSSO_NUMERO' => $args['NOSSO_NUMERO'],
						'NUMERO_DOCUMENTO' => $args['NUMERO_DOCUMENTO'],
						'DATA_VENCIMENTO' => $args['DATA_VENCIMENTO'],
						'VALOR' => $args['VALOR'],
						'TIPO_ESPECIE' => '99',
						'FLAG_ACEITE' => $args['FLAG_ACEITE'],
						'JUROS_MORA' => array(
							'TIPO' => 'ISENTO',
							'VALOR' => '0',
						),
						'VALOR_ABATIMENTO' => '0',
						'POS_VENCIMENTO' => array(
							'ACAO' => 'DEVOLVER',
							'NUMERO_DIAS' => $args['NUMERO_DIAS'],
						),
						'FICHA_COMPENSACAO' => $args['FICHA_COMPENSACAO']
					),
				)
			)
		);

		return $this->Manutencao($xml_array, 'ALTERA_BOLETO');
	}

	/*** Getters ***/

	function GetCodigoRetorno()      { return isset($this->resposta['DADOS']['CONTROLE_NEGOCIAL']['COD_RETORNO']) ?
													$this->resposta['DADOS']['CONTROLE_NEGOCIAL']['COD_RETORNO'] : 
													isset($this->resposta['COD_RETORNO']) ?
														  $this->resposta['COD_RETORNO'] : null; }
	function GetMensagemRetorno()    { return isset($this->resposta['DADOS']['CONTROLE_NEGOCIAL']['MSG_RETORNO']) ?
													$this->resposta['DADOS']['CONTROLE_NEGOCIAL']['MSG_RETORNO'] : 
													isset($this->resposta['MSG_RETORNO']) ?
													      $this->resposta['MSG_RETORNO'] : null; }
	function GetUrlBoleto()          { return isset($this->resposta['DADOS']['CONSULTA_BOLETO']['TITULO']['URL']) ?
									   			    $this->resposta['DADOS']['CONSULTA_BOLETO']['TITULO']['URL'] :
									  			  	isset($this->resposta['DADOS']['ALTERA_BOLETO']['URL']) ?
															$this->resposta['DADOS']['ALTERA_BOLETO']['URL'] : null; }
    function GetExcecao()            { return isset($this->resposta['DADOS']['EXCECAO']) ?
									   			    $this->resposta['DADOS']['EXCECAO'] : null; }
	function GetCedente()            { return $this->resposta['CEDENTE']; }
	function GetIdentificacao()      { return $this->resposta['IDENTIFICACAO']; }
	function GetCnpj()               { return $this->resposta['CNPJ']; }
	function GetEndereco1()          { return $this->resposta['ENDERECO1']; }
	function GetEndereco2()          { return $this->resposta['ENDERECO2']; }
	function GetUnidade()            { return $this->resposta['UNIDADE']; }
	function GetCodigoBeneficiario() { return $this->resposta['CODIGO_BENEFICIARIO']; }
	function GetDataEmissao()        { return $this->resposta['DADOS']['CONSULTA_BOLETO']['TITULO']['DATA_EMISSAO']; }
	function GetDataVencimento()     { return $this->resposta['DADOS']['CONSULTA_BOLETO']['TITULO']['DATA_VENCIMENTO']; }
	function GetValor()              { return $this->resposta['DADOS']['CONSULTA_BOLETO']['TITULO']['VALOR']; }
	function GetNossoNumero()        { return $this->resposta['NOSSO_NUMERO']; }
	function GetNumeroDocumento()    { return $this->resposta['DADOS']['CONSULTA_BOLETO']['TITULO']['NUMERO_DOCUMENTO']; }
	function GetFlagAceite()         { return $this->args['FLAG_ACEITE']; }
	function GetPagadorNome()        { return (isset($this->resposta['DADOS']['CONSULTA_BOLETO']['TITULO']['PAGADOR']['NOME'])) ?
													 $this->resposta['DADOS']['CONSULTA_BOLETO']['TITULO']['PAGADOR']['NOME'] :
													 $this->resposta['DADOS']['CONSULTA_BOLETO']['TITULO']['PAGADOR']['RAZAO_SOCIAL']; }
	function GetPagadorNumero()      { return (isset($this->resposta['DADOS']['CONSULTA_BOLETO']['TITULO']['PAGADOR']['CPF'])) ?
                                                     $this->resposta['DADOS']['CONSULTA_BOLETO']['TITULO']['PAGADOR']['CPF'] :
													 $this->resposta['DADOS']['CONSULTA_BOLETO']['TITULO']['PAGADOR']['CNPJ']; }
	function GetPagadorLogradouro()  { return $this->resposta['DADOS']['CONSULTA_BOLETO']['TITULO']['PAGADOR']['ENDERECO']['LOGRADOURO']; }
	function GetPagadorCidade()      { return $this->resposta['DADOS']['CONSULTA_BOLETO']['TITULO']['PAGADOR']['ENDERECO']['CIDADE']; }
	function GetPagadorBairro()      { return $this->resposta['DADOS']['CONSULTA_BOLETO']['TITULO']['PAGADOR']['ENDERECO']['BAIRRO']; }
	function GetPagadorUf()          { return $this->resposta['DADOS']['CONSULTA_BOLETO']['TITULO']['PAGADOR']['ENDERECO']['UF']; }
	function GetPagadorCep()         { return $this->resposta['DADOS']['CONSULTA_BOLETO']['TITULO']['PAGADOR']['ENDERECO']['CEP']; }
	function GetMensagem1()          { return (is_array($this->resposta['DADOS']['CONSULTA_BOLETO']['TITULO']['FICHA_COMPENSACAO']['MENSAGENS']['MENSAGEM'])) ?
                                                        $this->resposta['DADOS']['CONSULTA_BOLETO']['TITULO']['FICHA_COMPENSACAO']['MENSAGENS']['MENSAGEM'][0] :
                                                        $this->resposta['DADOS']['CONSULTA_BOLETO']['TITULO']['FICHA_COMPENSACAO']['MENSAGENS']['MENSAGEM'] ; }
	function GetMensagem2()          { return (is_array($this->resposta['DADOS']['CONSULTA_BOLETO']['TITULO']['FICHA_COMPENSACAO']['MENSAGENS']['MENSAGEM'])) ?
                                                        $this->resposta['DADOS']['CONSULTA_BOLETO']['TITULO']['FICHA_COMPENSACAO']['MENSAGENS']['MENSAGEM'][1] : '' ; }
}
