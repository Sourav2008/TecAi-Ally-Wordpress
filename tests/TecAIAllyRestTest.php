<?php

use PHPUnit\Framework\TestCase;

/**
 * Basic contract tests for TecAI_Ally_REST_Controller.
 */
class TecAIAllyRestTest extends TestCase {

    public function test_rest_controller_class_should_exist() {
        $this->assertTrue(
            class_exists( 'TecAI_Ally_REST_Controller' ),
            'TecAI_Ally_REST_Controller class should be defined.'
        );
    }

    public function test_rate_limiting_logic_contract() {
        if ( ! class_exists( 'TecAI_Ally_REST_Controller' ) ) {
            $this->markTestSkipped( 'TecAI_Ally_REST_Controller not implemented yet.' );
        }

        $ref = new ReflectionClass( 'TecAI_Ally_REST_Controller' );

        $this->assertTrue(
            $ref->hasMethod( 'is_rate_limited' ),
            'REST controller should expose is_rate_limited() for spam protection.'
        );
    }
}
