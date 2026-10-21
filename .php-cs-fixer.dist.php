<?php

$finder = new PhpCsFixer\Finder()
    ->in(__DIR__)
    ->exclude('var')
    ->notPath([
        'config/bundles.php',
        'config/reference.php',
    ])
;

return new PhpCsFixer\Config()
    ->setRules([
        'psr_autoloading' => false,
        'line_ending' => true,
        'short_scalar_cast' => true,
        'indentation_type' => true,
        'cast_spaces' => true,
        'no_closing_tag' => true,
        'no_blank_lines_after_phpdoc' => true,
        'no_blank_lines_after_class_opening' => true,
        'blank_line_before_statement' => true,
        'lowercase_keywords' => true,
        'no_trailing_whitespace_in_comment' => true,
        'no_useless_return' => true,
        'no_extra_blank_lines' => true,
        'elseif' => true,
        'function_declaration' => true,
        'blank_line_after_namespace' => true,
        'blank_line_after_opening_tag' => true,
        // Replaced deprecated 'braces' rule with new alternatives
        'single_space_around_construct' => true,
        'control_structure_braces' => true,
        'control_structure_continuation_position' => true,
        'declare_parentheses' => true,
        'no_multiple_statements_per_line' => true,
        'braces_position' => [
            'control_structures_opening_brace' => 'same_line',
            'functions_opening_brace' => 'next_line_unless_newline_at_signature_end',
            'anonymous_functions_opening_brace' => 'same_line',
            'classes_opening_brace' => 'next_line_unless_newline_at_signature_end',
            'anonymous_classes_opening_brace' => 'same_line',
            'allow_single_line_empty_anonymous_classes' => false,
            'allow_single_line_anonymous_functions' => false,
        ],
        'statement_indentation' => true,
        'full_opening_tag' => true,
        'no_unused_imports' => true,
        'no_whitespace_before_comma_in_array' => true,
        'trailing_comma_in_multiline' => true,
    ])
    ->setFinder($finder);
