<?php

namespace Drupal\kint\Twig;
use Drupal\devel\DevelDumperManagerInterface;

/**
 * Provides the Kint debugging function within Twig templates.
 */
class KintExtension extends \Twig_Extension {
  /**
  * The devel dumper service.
  *
   * @var \Drupal\devel\DevelDumperManagerInterface
   */
  protected $dumper;

  /**
   * Constructs a KintExtension object.
   *
   * @param \Drupal\devel\DevelDumperManagerInterface $dumper
   *   The devel dumper service.
   */
  public function __construct(DevelDumperManagerInterface $dumper) {
    $this->dumper = $dumper;
  }
  /**
   * {@inheritdoc}
   */
  public function getName() {
    return 'kint';
  }

  /**
   * {@inheritdoc}
   */
  public function getFunctions() {
    // return array(
    //   new \Twig_SimpleFunction('kint', array($this, 'kint'), array(
    //     'is_safe' => array('html'),
    return [
            new \Twig_SimpleFunction('kint', [$this, 'dump'], [
              'is_safe' => ['html'],
               'needs_environment' => TRUE,
               'needs_context' => TRUE,
               'is_variadic' => TRUE,
      ]),
    ];
  }

  /**
   * Provides Kint function to Twig templates.
   *
   * Handles 0, 1, or multiple arguments.
   *
   * @param \Twig_Environment $env
   *   The twig environment instance.
   * @param array $context
   *   An array of parameters passed to the template.
   * @param array $args
   *   An array of parameters passed the function.
   *
   * @return string|null
   *   String representation of the input variables.
   */
  public function dump(\Twig_Environment $env, array $context, array $args = []) {
    // Don't do anything unless twig_debug is enabled. This reads from the Twig
    // environment, not Drupal Settings, so a container rebuild is necessary
    // when toggling twig_debug on and off. We can consider injecting Settings.

  // public function dump(\Twig_Environment $env, array $context, array $args = []) {
  //   if (!$env->isDebug()) {
  //     return;
  //   }

    // Force displayCalledFrom to false. You should not use directly kint class
    // since the interaction with it should be completely delegated to the kint
    // plugin adapter but we have not other solutions.
    kint_require();
    $restore_called_from = \Kint::$displayCalledFrom;
    \Kint::$displayCalledFrom = FALSE;

      // $result = @\Kint::dump($kint_variable);
      // $output = str_replace('$kint_variable', 'Twig context', $result);
      // No arguments passed, display full Twig context.
    if (empty($args)) {
        $context_variables = $this->getContextVariables($context);
        $this->dumper->dump($context_variables, 'Twig context', 'kint');
    }
    else {
      // // Try to get the names of variables from the Twig template.
      // $parameters = $this->getTwigFunctionParameters();

      // // If there is only one argument, pass to Kint without too much hassle.
      // if (count($args) == 1) {
      //   $kint_variable = reset($args);
      //   $variable_name = reset($parameters);
      //   $result = @\Kint::dump($kint_variable);
      //   // Replace $kint_variable with the name of the variable in the Twig
      //   // template.
      //   $output = str_replace('$kint_variable', $variable_name, $result);
      $parameters = $this->guessTwigFunctionParameters();

      $variables = [];
      if (count($args) === 1) {
        $variables = reset($args);
      }
       else {
      // else {
      //   $kint_args = [];
      //   // Build an array of variable to pass to Kint.
      //   // @todo Can we just call_user_func_array while still retaining the
      //   //   variable names?
      //   foreach ($args as $index => $arg) {
      //     // Prepend a unique index to allow debugging the same variable more
      //     // than once in the same Kint dump.
      //     $name = !empty($parameters[$index]) ? $parameters[$index] : $index;
      //     $kint_args['_index_' . $index . '_' . $name] = $arg;
      foreach ($args as $key => $variable) {
        $variables[$parameters[$key]] = $variable;
      }

      //   $result = @\Kint::dump($kint_args);
      //   // Display a comma separated list of the variables contained in this group.
      //   $output = str_replace('$kint_args', implode(', ', $parameters), $result);
      //   // Remove unique indexes from output.
      //   $output = preg_replace('/_index_([0-9]+)_/', '', $output);
      // }
        $this->dumper->dump($variables, implode(', ', $parameters), 'kint');
      }
    }
    

    // return $output;
    $dump = ob_get_clean();

    // Restore original displayCalledFrom flag.
    \Kint::$displayCalledFrom = $restore_called_from;

    return $dump;
  }

  /**
   * Filters the Twig context variable.
   *
   * @param array $context
   *   The Twig context.
   *
   * @return array
   *   An array Twig context variables.
   */
  protected function getContextVariables(array $context) {
    $context_variables = [];
    foreach ($context as $key => $value) {
      if (!$value instanceof \Twig_Template) {
        $context_variables[$key] = $value;
      }
    }
    return $context_variables;
   }

  /**
  
   * Guess the twig function parameters for the current invocation.
    *
    * @return array
   *   The detected twig function parameters or an empty array.
    */
  // protected function getTwigFunctionParameters() {
  //   $callee = NULL;
  //   $template = NULL;
  protected function guessTwigFunctionParameters() {
    $callee = NULL;
    $template = NULL;

    $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS | DEBUG_BACKTRACE_PROVIDE_OBJECT);

    foreach ($backtrace as $index => $trace) {
      if (isset($trace['object']) && $trace['object'] instanceof \Twig_Template && 'Twig_Template' !== get_class($trace['object'])) {
        $template = $trace['object'];
        $callee = $backtrace[$index - 1];
        break;
      }
    }

    $parameters = [];

    /** @var \Twig_Template $template */
    if (NULL !== $template && NULL !== $callee && method_exists($template, 'getDebugInfo')) {
      $line_number = $callee['line'];
      $debug_infos = $template->getDebugInfo();
      $line_number = $callee['line'];
      $debug_infos = $template->getDebugInfo();

      if (isset($debug_infos[$line_number])) {
        $source_line = $debug_infos[$line_number];
        $source_file_name = $template->getTemplateName();

        if (is_readable($source_file_name)) {
          $source = file($source_file_name, FILE_IGNORE_NEW_LINES);
          $line = $source[$source_line - 1];

          preg_match('/kint\((.+)\)/', $line, $matches);
          if (isset($matches[1])) {
            $parameters = array_map('trim', explode(',', $matches[1]));
          }
        }
      }
    }

    return $parameters;
  }

}
