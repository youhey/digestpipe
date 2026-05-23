<?php

/** @see https://cs.symfony.com/doc/rules/index.html */

$finder = PhpCsFixer\Finder::create()
    ->name('*.php')
    ->name('*.phpt')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true)
    ->notName('*.blade.php')
    ->notName('_ide_helper.php')
    ->notName('_ide_macros.php')
    ->notName('.phpstorm.meta.php')
    ->exclude('bootstrap/cache')
    ->exclude('public')
    ->exclude('resources')
    ->exclude('storage')
    ->exclude('vendor')
    ->exclude('node_modules')
    ->in(__DIR__);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(false)
    ->setRules([
        '@PSR12' => true,
        '@PhpCsFixer' => true,

        'align_multiline_comment' => [
            'comment_type' => 'phpdocs_only',
        ],

        'array_syntax' => [
            'syntax' => 'short',
        ],

        'binary_operator_spaces' => [
            'default' => 'single_space',
        ],

        'blank_line_after_namespace' => true,
        'blank_line_after_opening_tag' => true,

        'blank_line_before_statement' => [
            'statements' => [
                'break',
                'continue',
                'declare',
                'return',
                'throw',
                'try',
            ],
        ],

        'braces_position' => [
            'allow_single_line_anonymous_functions' => true,
            'allow_single_line_empty_anonymous_classes' => true,
            'anonymous_classes_opening_brace' => 'same_line',
            'anonymous_functions_opening_brace' => 'same_line',
            'control_structures_opening_brace' => 'same_line',
            'functions_opening_brace' => 'next_line_unless_newline_at_signature_end',
        ],

        'cast_spaces' => [
            'space' => 'single',
        ],

        'class_attributes_separation' => [
            'elements' => [
                'const' => 'one',
                'method' => 'one',
                'property' => 'one',
                'trait_import' => 'one',
            ],
        ],

        'class_definition' => [
            'multi_line_extends_each_single_line' => true,
            'single_item_single_line' => true,
            'single_line' => false,
        ],

        'combine_consecutive_issets' => true,
        'combine_consecutive_unsets' => true,

        'compact_nullable_type_declaration' => true,

        'concat_space' => [
            'spacing' => 'one',
        ],

        'constant_case' => [
            'case' => 'lower',
        ],

        'control_structure_braces' => true,
        'control_structure_continuation_position' => true,
        'declare_parentheses' => true,

        'elseif' => true,
        'encoding' => true,
        'full_opening_tag' => true,

        'function_declaration' => [
            'closure_function_spacing' => 'one',
        ],

        'fully_qualified_strict_types' => false,

        'general_phpdoc_annotation_remove' => [
            'annotations' => [],
        ],

        'global_namespace_import' => [
            'import_classes' => true,
            'import_constants' => false,
            'import_functions' => false,
        ],

        'heredoc_to_nowdoc' => true,

        'include' => true,

        'increment_style' => [
            'style' => 'pre',
        ],

        'indentation_type' => true,
        'line_ending' => true,
        'linebreak_after_opening_tag' => true,

        'list_syntax' => [
            'syntax' => 'short',
        ],

        'lowercase_cast' => true,
        'lowercase_keywords' => true,

        'method_argument_space' => [
            'on_multiline' => 'ensure_fully_multiline',
            'keep_multiple_spaces_after_comma' => false,
        ],

        'multiline_whitespace_before_semicolons' => [
            'strategy' => 'no_multi_line',
        ],

        'new_with_parentheses' => [
            'anonymous_class' => true,
            'named_class' => true,
        ],

        'no_blank_lines_after_class_opening' => true,
        'no_blank_lines_after_phpdoc' => true,
        'no_break_comment' => true,
        'no_closing_tag' => true,
        'no_empty_comment' => true,
        'no_empty_phpdoc' => true,
        'no_empty_statement' => true,
        'no_extra_blank_lines' => [
            'tokens' => [
                'break',
                'case',
                'continue',
                'curly_brace_block',
                'default',
                'extra',
                'parenthesis_brace_block',
                'return',
                'square_brace_block',
                'switch',
                'throw',
            ],
        ],
        'no_leading_import_slash' => true,
        'no_leading_namespace_whitespace' => true,
        'no_mixed_echo_print' => [
            'use' => 'echo',
        ],
        'no_multiple_statements_per_line' => true,
        'no_multiline_whitespace_around_double_arrow' => true,
        'no_php4_constructor' => false,
        'no_short_bool_cast' => true,
        'no_singleline_whitespace_before_semicolons' => true,
        'no_spaces_after_function_name' => true,
        'no_superfluous_elseif' => true,
        'no_superfluous_phpdoc_tags' => false,
        'no_trailing_comma_in_singleline' => [
            'elements' => [
                'arguments',
                'array',
                'array_destructuring',
                'group_import',
            ],
        ],
        'no_trailing_whitespace' => true,
        'no_trailing_whitespace_in_comment' => true,
        'no_unneeded_control_parentheses' => [
            'statements' => [
                'break',
                'clone',
                'continue',
                'echo_print',
                'switch_case',
            ],
        ],
        'no_unused_imports' => true,
        'no_useless_else' => true,
        'no_useless_return' => true,
        'no_whitespace_before_comma_in_array' => true,
        'no_whitespace_in_blank_line' => true,

        'not_operator_with_space' => false,
        'not_operator_with_successor_space' => true,

        'nullable_type_declaration_for_default_null_value' => true,

        'ordered_imports' => [
            'imports_order' => [
                'class',
                'function',
                'const',
            ],
            'sort_algorithm' => 'alpha',
        ],

        'phpdoc_align' => [
            'align' => 'left',
            'tags' => [
                'param',
                'return',
                'throws',
                'type',
                'var',
            ],
        ],
        'phpdoc_indent' => true,
        'phpdoc_inline_tag_normalizer' => [
            'tags' => [
                'example',
                'id',
                'internal',
                'inheritdoc',
                'inheritdocs',
                'link',
                'source',
                'toc',
                'tutorial',
            ],
        ],
        'phpdoc_line_span' => [
            'const' => 'single',
            'method' => 'multi',
            'property' => 'single',
        ],
        'phpdoc_no_alias_tag' => [
            'replacements' => [
                'type' => 'var',
                'link' => 'see',
            ],
        ],
        'phpdoc_no_empty_return' => false,
        'phpdoc_return_self_reference' => [
            'replacements' => [
                'this' => '$this',
                '@this' => '$this',
                '$self' => 'self',
                '@self' => 'self',
                '$static' => 'static',
                '@static' => 'static',
            ],
        ],
        'phpdoc_scalar' => [
            'types' => [
                'boolean',
                'double',
                'integer',
            ],
        ],
        'phpdoc_separation' => true,
        'phpdoc_single_line_var_spacing' => true,
        'phpdoc_summary' => false,
        'phpdoc_tag_type' => [
            'tags' => [
                'api' => 'annotation',
                'author' => 'annotation',
                'copyright' => 'annotation',
                'deprecated' => 'annotation',
                'example' => 'annotation',
                'global' => 'annotation',
                'inheritDoc' => 'inline',
                'internal' => 'annotation',
                'license' => 'annotation',
                'method' => 'annotation',
                'package' => 'annotation',
                'param' => 'annotation',
                'property' => 'annotation',
                'return' => 'annotation',
                'see' => 'annotation',
                'since' => 'annotation',
                'throws' => 'annotation',
                'todo' => 'annotation',
                'uses' => 'annotation',
                'var' => 'annotation',
                'version' => 'annotation',
            ],
        ],
        'phpdoc_to_comment' => false,
        'phpdoc_trim' => true,
        'phpdoc_trim_consecutive_blank_line_separation' => true,
        'phpdoc_types' => [
            'groups' => [
                'simple',
                'alias',
                'meta',
            ],
        ],
        'phpdoc_types_order' => [
            'null_adjustment' => 'always_last',
            'sort_algorithm' => 'none',
        ],
        'phpdoc_var_annotation_correct_order' => true,
        'phpdoc_var_without_name' => true,

        'return_type_declaration' => [
            'space_before' => 'none',
        ],

        'single_class_element_per_statement' => [
            'elements' => [
                'property',
                'const',
            ],
        ],
        'single_line_comment_style' => [
            'comment_types' => [
                'hash',
            ],
        ],
        'single_line_empty_body' => false,

        'single_quote' => [
            'strings_containing_single_quote_chars' => false,
        ],

        'single_space_around_construct' => true,

        'space_after_semicolon' => [
            'remove_in_empty_for_expressions' => true,
        ],

        'spaces_inside_parentheses' => true,
        'statement_indentation' => [
            'stick_comment_to_next_continuous_control_statement' => false,
        ],

        'ternary_operator_spaces' => true,

        'trailing_comma_in_multiline' => [
            'after_heredoc' => false,
            'elements' => [
                'arrays',
            ],
        ],

        'type_declaration_spaces' => [
            'elements' => [
                'function',
                'property',
            ],
        ],

        'whitespace_after_comma_in_array' => true,

        'yoda_style' => false,

        // Keep PHPUnit tests lightweight unless explicit coverage requirements are introduced.
        'php_unit_test_class_requires_covers' => false,

        // Avoid PHP 8.4-style `new Foo()->bar()` conversion for now.
        'new_expression_parentheses' => false,
    ])
    ->setUsingCache(true)
    ->setParallelConfig(PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect())
    ->setFinder($finder);
