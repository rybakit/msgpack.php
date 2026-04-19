<?php

namespace MessagePack;

use PhpCsFixer\Config;
use PhpCsFixer\Fixer\ConfigurableFixerInterface;
use PhpCsFixer\Fixer\ConstantNotation\NativeConstantInvocationFixer;
use PhpCsFixer\Fixer\FunctionNotation\NativeFunctionInvocationFixer;
use PhpCsFixer\FixerConfiguration\FixerConfigurationResolver;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\Tokens;

final class FilterableFixer implements ConfigurableFixerInterface
{
    private ConfigurableFixerInterface $fixer;
    private string $pathRegex;

    public function __construct(ConfigurableFixerInterface $fixer, string $pathRegex)
    {
        $this->fixer = $fixer;
        $this->pathRegex = $pathRegex;
    }

    public function configure(array $configuration): void
    {
        $this->fixer->configure($configuration);
    }

    public function getConfigurationDefinition(): FixerConfigurationResolver
    {
        return $this->fixer->getConfigurationDefinition();
    }

    public function isCandidate(Tokens $tokens) : bool
    {
        return $this->fixer->isCandidate($tokens);
    }

    public function isRisky() : bool
    {
        return $this->fixer->isRisky();
    }

    public function fix(\SplFileInfo $file, Tokens $tokens) : void
    {
        $this->fixer->fix($file, $tokens);
    }

    public function getName() : string
    {
        return (new \ReflectionClass($this))->getShortName().'/'.$this->fixer->getName();
    }

    public function getPriority() : int
    {
        return $this->fixer->getPriority();
    }

    public function supports(\SplFileInfo $file) : bool
    {
        if (1 !== preg_match($this->pathRegex, $file->getRealPath())) {
            return false;
        }

        return $this->fixer->supports($file);
    }

    public function getDefinition() : FixerDefinitionInterface
    {
        return $this->fixer->getDefinition();
    }
}

$header = <<<EOF
This file is part of the rybakit/msgpack.php package.

(c) Eugene Leonovich <gen.work@gmail.com>

For the full copyright and license information, please view the LICENSE
file that was distributed with this source code.
EOF;

return (new Config())
    ->setUsingCache(false)
    ->setRiskyAllowed(true)
    ->registerCustomFixers([
        new FilterableFixer(new NativeConstantInvocationFixer(), '/\bsrc|examples\/MessagePack\b/'),
        new FilterableFixer(new NativeFunctionInvocationFixer(), '/\bsrc|examples\/MessagePack\b/'),
    ])
    ->setRules([
        '@Symfony' => true,
        '@Symfony:risky' => true,
        'array_syntax' => ['syntax' => 'short'],
        'binary_operator_spaces' => ['operators' => ['=' => null, '=>' => null]],
        'fully_qualified_strict_types' => false,
        'integer_literal_case' => false,
        'is_null' => false,
        'native_constant_invocation' => false,
        'native_function_invocation' => false,
        'FilterableFixer/native_constant_invocation' => ['strict' => false],
        'FilterableFixer/native_function_invocation' => ['strict' => false],
        'no_useless_else' => true,
        'no_useless_return' => true,
        'no_useless_concat_operator' => false,
        'no_superfluous_phpdoc_tags' => false,
        'ordered_imports' => [
            'sort_algorithm' => 'alpha',
            'imports_order' => ['class', 'function', 'const'],
        ],
        'phpdoc_align' => false,
        'phpdoc_order' => true,
        'phpdoc_to_comment' => false,
        'return_type_declaration' => ['space_before' => 'one'],
        'statement_indentation' => false,
        'strict_comparison' => true,
        'header_comment' => [
            'comment_type' => 'PHPDoc',
            'header' => $header,
            'location' => 'after_open',
            'separate' => 'both',
        ],
    ])
;
