<?php
/**
 * Consulta e registro de boletos da Caixa Econômica Federal
 *
 * Estrutura de diretórios:
 *
 *   /
 *     |-- /lib                   Bibliotecas utilizadas
 *     |-- /xml                   Arquivos WSDL e XSD enviados pela CEF
 *     |-- webservice-caixa.php   Biblioteca
 *
 * Exemplo de uso:
 *
 * include('WebserviceCaixa/WebserviceCaixa.php');
 * $ws = new WebserviceCaixa($array_de_argumentos);
 * $ws->Consulta(); $ws->Gera();
 *
 */

define('RETRIES',  20);                 // número de tentativas de conexão com o WS antes de falhar
define('TIMEOUT',  5);                  // timeout para desistir da resposta
define('INTERVAL', 1.5);                // intervalo entre tentativas

// informações que serão impressas no cabeçalho do boleto
define('CEDENTE', 'NOME DO CEDENTE');
define('IDENTIFICACAO', 'IDENTIFICACAO DO CEDENTE NO CABECALHO');
define('CNPJ', '999999999999999');
define('ENDERECO1', 'PRIMEIRA LINHA DE ENDERECO');
define('ENDERECO2', 'SEGUNDA LINHA DE ENDERECO');

define('UNIDADE', '9999');			// código de agência de relacionamento

define('HASH_DEBUG', 'HASH SECRETO PARA DEBUG'); // exibe informações do boleto quando `?hash=` é informado

define('DIR', 'WebserviceCaixa'); // diretório do servidor em que este arquivo é colocado

include(dirname(__FILE__) . '/lib/nusoap/lib/nusoap.php');
include(dirname(__FILE__) . '/lib/XmlDomConstruct.php');

class WebserviceCaixa {

	var $args;
	var $consulta;

	/**
	 * Construtor atribui e formata parâmetros em $this->args
	 */
	function __construct($args) {

		// Ambiente de desenvolvimento ou $_GET['DEBUG'] informado adequadamente
		$this->dev = isset($_GET['DEBUG']) && $_GET['DEBUG'] == HASH_DEBUG);
		
		// Localização HTTP dos arquivos WSDL
		$this->wsdl_consulta = $this->GetBaseUrl() . DIR . '/xml/Consulta_Cobranca_Bancaria_Boleto.wsdl';
		$this->wsdl_manutencao = $this->GetBaseUrl() . DIR . '/xml/Manutencao_Cobranca_Bancaria_Externo.wsdl';

		// Campos padrões
		$padroes = array(
			'IDENTIFICADOR_ORIGEM' => $_SERVER['REMOTE_ADDR'],
			'UNIDADE' => UNIDADE
		);
		
		$this->args = $this->CleanArray(array_merge($padroes,$args));
		
		// Informações acessíveis aos getters
		$this->consulta['CEDENTE'] = CEDENTE;
		$this->consulta['IDENTIFICACAO'] = IDENTIFICACAO;
		$this->consulta['ENDERECO1'] = ENDERECO1;
		$this->consulta['ENDERECO2'] = ENDERECO2;
		$this->consulta['CNPJ'] = CNPJ;
		$this->consulta['UNIDADE'] = $this->args['UNIDADE'];
		$this->consulta['CODIGO_BENEFICIARIO'] = $this->args['CODIGO_BENEFICIARIO'];
		$this->consulta['NOSSO_NUMERO'] = $this->args['NOSSO_NUMERO'];
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

	function GetBaseUrl() {
		return sprintf(
			"%s://%s",
			isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 'https' : 'http',
			$_SERVER['SERVER_NAME']
		);
	}

	/**
	 * Formata uma mensagem de erro na tela
	 *
	 * @param $txt Exibido ao usuário
	 * @param $log Exibido ao desenvolvedor quando em ambiente de
	 *             desenvolvimento ou quando $_GET['DEBUG'] é passado
	 *             adequadamente
	 */
	function ExibeErro($txt = '') {
		if ($txt == 'INDISP') {
			$txt = 'O sistema de boletos da Caixa Econômica Federal encontra-se indisponível. Tente acessar o link mais tarde.';
		} else if ($txt == '') {
			$txt = "Houve um erro ao gerar o boleto. Por favor, visite esta página mais tarde.";
		}
		if ($this->dev) {
			ob_start();
			print_r($this);
			$_this = ob_get_clean();
		}
		?>
		<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
		<html lang="pt-BR" xmlns="http://www.w3.org/1999/xhtml">
		<head><style type="text/css">
			pre { background-color: #EAEAEA; padding: 10px; }
			.mensagem { font-family: Arial; font-size: 16px; padding: 20px; background-color: #595150; color: white; opacity: 0.83; transition: opacity 0.6s; margin-bottom: 15px; }
			.mensagem a { color: white; }
		</style></head>
		<body>
			<div class="mensagem"><?php echo $txt; ?></div>
			<?php if ($this->dev && $_this) : ?><pre><?php echo $_this; ?></pre><?php endif; ?>
		</body></html>
		<?php
		exit();
	}

	/**
	 * Encapsulamento da chamada do NuSOAP ao WebService
	 *
	 * Devido à instabilidade do serviço, faz consultas repetidas até o
	 * número de tentativas definido em RETRIES. Deve ser usado ao invés do
	 * método `nusoap_client->call` da biblioteca.
	 */
	function CallNuSOAP($wsdl, $operacao, $conteudo) {
		$client = new nusoap_client($wsdl, $wsdl = true, $timeout = TIMEOUT);
		// @TODO implementar consulta com certificado
		$client->curl_options = array('insecure' => true);
		$done = false;
		$retries = 0;

		while (!$done) {		
			if (++$retries > RETRIES)
				$this->ExibeErro('INDISP');

			$response = $client->call($operacao, $conteudo, $retries);
			$err = $client->getError();
			
			if ($this->dev)
				$this->debug['RETORNO_CAIXA'][] = array(
					'RESPOSTA' => $response,
					'ERRO' => $err
				);

			if (!$client->fault && !$err) {
				$done = true;
			} else {
				sleep(INTERVAL);
			}
		}

		return $response;
	}

	/**
	 * Faz a chamada ao WebService verificando as mensagens de erro
	 * documentadas no manual.
	 *
	 * Código de retorno '02' são erros indisponibilidade na ponta (Pág. 35)
	 *
	 * Demais códigos de retorno (Págs. 33 a 35) devem ser checados pela
	 * rotina que invoca este método
	 */
	function Call($wsdl, $operacao, $conteudo) {
		$response = $this->CallNusoap($wsdl, $operacao, $conteudo);
		$codret = $this->GetCodigoRetorno($response);

		// Código 0 = operação efetuada
		if ($codret === '0')
			return $response;

		/* Erros próprios de sistema (Pág. 35) que acarretam em erros fatais
		 *   - Código 02 = sistema indisponível
		 *   - Código X5 = formatação de mensagem
		 */
		$cod_erros = array('02', 'X5');
		if (isset($response['COD_RETORNO']) && in_array($response['COD_RETORNO'], $cod_erros)) {
			$this->ExibeErro('INDISP');
		}

		/* Erros de negócio (Págs. 33 a 35) que devem ser tratados pela
		 * rotina que invoca esta chamada
		 */
		if (isset($response['DADOS']['CONTROLE_NEGOCIAL']['MENSAGENS']['RETORNO'])) {
			if (preg_match('/\((.+)\).*/', $response['DADOS']['CONTROLE_NEGOCIAL']['MENSAGENS']['RETORNO'], $m)) {
				$response['COD_RETORNO'] = $m[1];
				$response['MSG_RETORNO'] = $response['DADOS']['CONTROLE_NEGOCIAL']['MENSAGENS']['RETORNO'];

				return $response;
			}
		}

		$this->ExibeErro('INDISP');
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
			sprintf('%014d', CNPJ));
		return base64_encode(hash('sha256', $raw, true));
	}

	/**
	 * Construção do documento XML para consultas.
	 */
	function ConsultaXML($args) {
		$xml_root = 'consultacobrancabancaria:SERVICO_ENTRADA';
		$xml = new XmlDomConstruct('1.0', 'iso-8859-1');
		$xml->preserveWhiteSpace = !$this->dev;
		$xml->formatOutput = $this->dev;
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
				'USUARIO_SERVICO' => 'SGCBS02P',
				'OPERACAO' => 'CONSULTA_BOLETO',
				'SISTEMA_ORIGEM' => 'SIGCB',
				'UNIDADE' => $args['UNIDADE'],
				'IDENTIFICADOR_ORIGEM' => $args['IDENTIFICADOR_ORIGEM'],
				'DATA_HORA' => date('YmdHis'),
				'ID_PROCESSO' => $args['ID_PROCESSO']
			),
			'DADOS' => array(
				'CONSULTA_BOLETO' => array(
					'CODIGO_BENEFICIARIO' => $args['CODIGO_BENEFICIARIO'],
					'NOSSO_NUMERO' => $args['NOSSO_NUMERO'],
				)
			)
		);

		$this->consulta = array_merge($this->consulta, $this->Call($this->wsdl_consulta, 'CONSULTA_BOLETO', $this->ConsultaXml($xml_array)));

		return $this->consulta;
	}

	/**
	 * Construção do documento XML para operações de manutenção
	 *
	 * Operações de inclusão e alteração
	 */
	function ManutencaoXml($args) {
		$xml_root = 'manutencaocobrancabancaria:SERVICO_ENTRADA';
		$xml = new XmlDomConstruct('1.0', 'iso-8859-1');
		$xml->preserveWhiteSpace = !$this->dev;
		$xml->formatOutput = $this->dev;
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

		return $this->Call($this->wsdl_manutencao, $operacao, $this->ManutencaoXml($xml_array));
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
				'USUARIO_SERVICO' => 'SGCBS02P',
				'OPERACAO' => 'INCLUI_BOLETO',
				'SISTEMA_ORIGEM' => 'SIGCB',
				'UNIDADE' => $args['UNIDADE'],
				'IDENTIFICADOR_ORIGEM' => OUT_IP,
				'DATA_HORA' => date('YmdHis'),
				'ID_PROCESSO' => $args['ID_PROCESSO'],
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
				'USUARIO_SERVICO' => 'SGCBS02P',
				'OPERACAO' => 'ALTERA_BOLETO',
				'SISTEMA_ORIGEM' => 'SIGCB',
				'UNIDADE' => $args['UNIDADE'],
				'IDENTIFICADOR_ORIGEM' => OUT_IP,
				'DATA_HORA' => date('YmdHis'),
				'ID_PROCESSO' => $args['ID_PROCESSO'],
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

	/**
	 * Obtém o código de retorno com o status das respostas do webservice
	 */
	function GetCodigoRetorno($response) {
		if (isset($response['DADOS']['CONTROLE_NEGOCIAL']['COD_RETORNO']))
			return intval($response['DADOS']['CONTROLE_NEGOCIAL']['COD_RETORNO']);

		return null;
	}

	/**
	 * Obtém url para impressão do boleto
	 */
	function GetUrlBoleto($response) {
		if (isset($response['DADOS']['CONSULTA_BOLETO']['TITULO']['URL']))
			return $response['DADOS']['CONSULTA_BOLETO']['TITULO']['URL'];
		if (isset($response['DADOS']['ALTERA_BOLETO']['URL']))
			return $response['DADOS']['ALTERA_BOLETO']['URL'];

		return null;
	}

	/**
	 * Verifica se o link está funcional
	 */
	function ChecaUrl($url) {
		$headers = get_headers($url);
		if (preg_match('/HTTP\/1.1 500.*/', $headers[0]))
			$this->ExibeErro('Boleto indisponível. Não foi possível recuperar o <a target="_blank" href="' . $url . '">link de impressão</a> da CEF.');
	}

	/**
	 * Wrapper para geração de boletos que deve ser utilizada externamente
	 * regida por $args['FORMATO_RETORNO']
	 *     - ARRAY retorna informações do boleto em vetor associativo
	 *     - REDIRECIONAMENTO envia o usuário para o PDF da Caixa na fonte
	 *     - URL string da url do boleto da Caixa
	 *     - DADOS string de dados binários do boleto da Caixa
	 */
	function Gera() {
		$boleto = $this->GeraBoleto();

		if (!$url = $this->GetUrlBoleto($boleto))
			$this->ExibeErro('Boleto falhou ao ser gerado.');

		switch ($this->args['FORMATO_RETORNO']) {
			case 'ARRAY':
				return $boleto;
				break;
			case 'REDIRECIONAMENTO':
				$this->ChecaUrl($url);
				header('Location:' . $url);
				exit();
				break;
			case 'URL':
				$this->ChecaUrl($url);
				return $url;
				break;
			case 'DADOS':
				$this->ChecaUrl($url);
				return file_get_contents($url);
				break;
		}
	}

	/**
	 * Geração dos boletos como exemplo segundo regra específica:
	 *
	 *   * se existe e está dentro do prazo de validade, não faz nada
	 *   * se existe e está fora do prazo de validade, altera data para hoje
	 *   * se não existe, inclui um novo
	 */
	function GeraBoleto() {
		$consulta = $this->Consulta($this->args);
		$codret = $this->GetCodigoRetorno($consulta);

		// Boleto registrado
		if ($codret == 0) {
			$vencimento = strtotime($consulta['DADOS']['CONSULTA_BOLETO']['TITULO']['DATA_VENCIMENTO']);

			// Dentro do prazo, somente retorna informações
			if ($vencimento >= strtotime('today'))
				return $consulta;

			$altera = $this->Altera($this->args);
			if ($this->GetCodigoRetorno($altera) == 0)
				return $altera;

			// Situações em que se deve registrar um novo nosso número
			$cods = array(
				47, // NOSSO NUMERO NAO CADASTRADO PARA O BENEFICIARIO
				48, // ALTERACAO NAO PERMITIDA - APENAS TITULOS "EM ABERTO" PODEM SER ALTERADOS
			);
			if (in_array($altera['COD_RETORNO'], $cods)) {
				// Parâmetro '1' ao final indica que um novo NOSSO_NUMERO deve ser gerado
				// aqui devem ser inseridas novas informações da baixa para alteração
				// do boleto
				/*
				$baixado_args = array();
				$baixado = $this->Altera($baixado_args);
				if ($this->GetCodigoRetorno($baixado) == 0)
					return $baixado;
				*/
			}

		}

		// Boleto não registrado
		if ($codret == 1) {
			$this->Inclui($this->args);
			return $this->Consulta($this->args);
		}

	}
	
	/*** Getters ***/
	function GetCedente()            { return $this->consulta['CEDENTE']; }
	function GetIdentificacao()      { return $this->consulta['IDENTIFICACAO']; }
	function GetCnpj()               { return $this->consulta['CNPJ']; }
	function GetEndereco1()          { return $this->consulta['ENDERECO1']; }
	function GetEndereco2()          { return $this->consulta['ENDERECO2']; }
	function GetUnidade()            { return $this->consulta['UNIDADE']; }
	function GetCodigoBeneficiario() { return $this->consulta['CODIGO_BENEFICIARIO']; }
	function GetDataEmissao()        { return $this->consulta['DADOS']['CONSULTA_BOLETO']['TITULO']['DATA_EMISSAO']; }
	function GetDataVencimento()     { return $this->consulta['DADOS']['CONSULTA_BOLETO']['TITULO']['DATA_VENCIMENTO']; }
	function GetValor()              { return $this->consulta['DADOS']['CONSULTA_BOLETO']['TITULO']['VALOR']; }
	function GetNossoNumero()        { return $this->consulta['NOSSO_NUMERO']; }
	function GetNumeroDocumento()    { return $this->consulta['DADOS']['CONSULTA_BOLETO']['TITULO']['NUMERO_DOCUMENTO']; }
	function GetFlagAceite()         { return $this->args['FLAG_ACEITE']; }
	function GetPagadorNome()        { return (isset($this->consulta['DADOS']['CONSULTA_BOLETO']['TITULO']['PAGADOR']['NOME'])) ?
													 $this->consulta['DADOS']['CONSULTA_BOLETO']['TITULO']['PAGADOR']['NOME'] :
													 $this->consulta['DADOS']['CONSULTA_BOLETO']['TITULO']['PAGADOR']['RAZAO_SOCIAL']; }
	function GetPagadorNumero()      { return (isset($this->consulta['DADOS']['CONSULTA_BOLETO']['TITULO']['PAGADOR']['CPF'])) ?
                                                     $this->consulta['DADOS']['CONSULTA_BOLETO']['TITULO']['PAGADOR']['CPF'] :
													 $this->consulta['DADOS']['CONSULTA_BOLETO']['TITULO']['PAGADOR']['CNPJ']; }
	function GetPagadorLogradouro()  { return $this->consulta['DADOS']['CONSULTA_BOLETO']['TITULO']['PAGADOR']['ENDERECO']['LOGRADOURO']; }
	function GetPagadorCidade()      { return $this->consulta['DADOS']['CONSULTA_BOLETO']['TITULO']['PAGADOR']['ENDERECO']['CIDADE']; }
	function GetPagadorBairro()      { return $this->consulta['DADOS']['CONSULTA_BOLETO']['TITULO']['PAGADOR']['ENDERECO']['BAIRRO']; }
	function GetPagadorUf()          { return $this->consulta['DADOS']['CONSULTA_BOLETO']['TITULO']['PAGADOR']['ENDERECO']['UF']; }
	function GetPagadorCep()         { return $this->consulta['DADOS']['CONSULTA_BOLETO']['TITULO']['PAGADOR']['ENDERECO']['CEP']; }
	function GetMensagem1()          { return (is_array($this->consulta['DADOS']['CONSULTA_BOLETO']['TITULO']['FICHA_COMPENSACAO']['MENSAGENS']['MENSAGEM'])) ?
                                                        $this->consulta['DADOS']['CONSULTA_BOLETO']['TITULO']['FICHA_COMPENSACAO']['MENSAGENS']['MENSAGEM'][0] :
                                                        $this->consulta['DADOS']['CONSULTA_BOLETO']['TITULO']['FICHA_COMPENSACAO']['MENSAGENS']['MENSAGEM'] ; }
	function GetMensagem2()          { return (is_array($this->consulta['DADOS']['CONSULTA_BOLETO']['TITULO']['FICHA_COMPENSACAO']['MENSAGENS']['MENSAGEM'])) ?
                                                        $this->consulta['DADOS']['CONSULTA_BOLETO']['TITULO']['FICHA_COMPENSACAO']['MENSAGENS']['MENSAGEM'][1] : '' ; }
}
