<?php
/**
 * Created by PhpStorm.
 * User: Administrador
 * Date: 12/8/2020
 * Time: 3:34 PM
 */

namespace Core;

use Core\CORS\CorsMiddleware;
use Core\DI\Resolver;
use Core\Renderer\PHPRendererInterface;

class App
{

  /**
   * Depachante.
   * Camada responsavel por delegar controle das rotas e requisicoes
   * @property Dispatcher
   */
  private $_dispatcher;

  /**
   * @property CorsMiddleware
   */
  public static $CORS;

  /**
   * @property PHPRendererInterface
   */
  private $renderer;

  public function __construct()
  {

    /**
     * Carregando Helpers/Ajudantes
     */
    require_once 'Helpers/functions.helpers.php';


    /**
     * Codificacao charset default
     */
    // @header('Content-Type: text/html; charset=UTF-8'); # LIB EMERGENCIAL PARA UTF-8 https://github.com/neitanod/forceutf8


    /**
     * Manipulador da Camada Dispatcher()
     */
    $this->_dispatcher = new \Core\Router\Dispatcher();

  }


  /**
   * Despachante para controladores
   */
  public function dispatcher()
  {
    /**
     * pega o retorno do dispatcher ja resolvido com callback e params da rota.
     */
    $result = $this->_dispatcher->run();

    if (!$result)
      exit('Rota não encontrada');


    $method = $result['callback'];
    $params = $result['params'];


    $this->run($method, $params);

    return $this;

  }

  /**
   * Defines Renderer strategy
   *
   * @param PHPRendererInterface $renderer
   * @return void
   */
  public function setRender(PHPRendererInterface $renderer)
  {
    $this->renderer = $renderer;
    return $this;
  }

  /**
   * Endpoint da aplicação.
   *
   * Neste ponto, o fluxo da aplicacao foi finalizado.
   * O controller e action serão executados de acordo com o metodo function(callable) ou controller(class@method)
   *
   * @param $method
   * @param $params
   * @throws \Exception
   */
  private function run($method, $params)
  {
    $data = null;
    /**
     * Verifica se a rota é string e com padrão regex /^([A-Z]{1}[a-z]+Controller)@([a-z]+)$/
     * Construção de critério para casa controladores.
     */
    if (is_string($method)) {

      $result = $this->checkControllerAction($method);

      if ($result['controller'] && $result['action']) {

        $class = $result['controller'];
        $method = $result['action'];

        /**
         *
         * Os parametros dos controllers sendo outros os objetos não são instanciado automaticamente.
         * Para isso, usamos uma padrão de projeto Dependency Injection(Injeção de Dependencia ou DI).
         * Isto garante que todas as classes passadas por parametro
         * serão "resolvidas" ou instanciadas (new Class()).
         *
         * Neste caso, estamos resolvendo uma classe e os parametros do seu construtor(__construct())
         * @see https://php-di.org/doc/understanding-di.html
         */
        $resolver = new Resolver();
        $instanceController = $resolver->byClass($class);

        /**
         * Precisaremos além da instancia da nossa classe de Controller.
         * Agora vamos resolver os parametros do método que a rota executará.
         *
         * Ex: Router::get('/admin','AdminController@index');
         * public function index( Request $request ) { }
         *
         * Precisaremos autoinstanciar o parametro $request de acordo com o tipo "Request"
         *
         */
        $method_dependencies = $resolver->method($class,$method);

        /**
         *  Observem que os parametros são do tipo array e estão sendo combinados
         *  Exemplo de união de arrays com sinal '+'
         */
        $params = $method_dependencies + $params;

        /**
         * Invocando metodos dinamicamente e passando parametros resolvidos e da requisição(GET,POST, ETC).
         */
        $data = call_user_func_array([$instanceController, $method], $params);

      } else {

        throw new \Exception('Voce passou um Controller/action invalido:' . $result['controller'] . '@' . $result['action'] . 'Insira uma string com o seguinte padrao: HomeController@index');

      }


    }

    /**
     *  Verifica se o conteúdo da variável pode ser chamado como função(callable)
     */
    if (is_callable($method)) {
      $data = call_user_func_array($method, $params);
    }

    /**
     * Capacitar ao PHPRenderer manipular cabeçalho antes de renderiza-lo
     */
    if(self::$CORS){
      $this->renderer->setCORS(self::$CORS);
    }

    

    /**
     * Capturando 
     */
    $this->renderer->setData($data);// Pega o retorno resolvido da Rota (Incluindo params) e seta como conteudo do renderer
    $this->renderer->run();
  }

  /**
   * Valida se string passada equivale a convenção(regra) do nosso microframework
   * Para usar Controllers é preciso usar o seguinte padrão: MinhaClasseController@meumetodo
   * @param $subject
   * @return array
   */
  private function checkControllerAction($subject)
  {

    /**
     * Allow namespace match case into my routes
     */
    $namespace = null;
    if(preg_match('/^([A-Z]{1}[a-z]+\\\)+/',$subject,$vars)){
      $namespace = $vars[0] . '\\';
    }

    /**
     * If has namespace matched into string $subject 
     * includes string $namespace in new pattern regex
     */
    $pattern = "/^({$namespace}([A-Z]{1}[a-z]+)+Controller)@([a-z]{1}[a-zA-Z0-9_-]+)$/"; 

    /**
     * checks if has routes with/without namespace spaces handled aboved
     */
    if (preg_match($pattern, $subject, $variables)) {

      if (!empty($variables[0])) {
        $controller = $variables[1];
        $action = $variables[3];
        $target_controller = "App\\Controllers\\" . $controller;

        if(!class_exists($target_controller,true)){
          throw new \Exception('Namespace ou Classe não existe');
        }

        return [
          'controller' => $target_controller,
          'action' => $action
        ];
      }

      return [];

    }

  }

}
