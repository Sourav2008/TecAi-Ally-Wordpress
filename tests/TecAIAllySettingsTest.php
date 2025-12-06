<?php

use PHPUnit\Framework\TestCase;

/**
 * Basic contract tests for TecAI_Ally_Settings.
 */
class TecAIAllySettingsTest extends TestCase {

    public function test_settings_class_should_be_loadable() {
        $this->assertTrue(
            class_exists( 'TecAI_Ally_Settings' ),
            'TecAI_Ally_Settings class should be defined and autoloadable.'
        );
    }

    public function test_api_key_is_sanitized() {
        if ( ! class_exists( 'TecAI_Ally_Settings' ) ) {
           $this->markTestSkipped( 'TecAI_Ally_Settings not implemented yet.' );
        }

        $settings  = new TecAI_Ally_Settings();
        $raw       = "  test-key \n";
        $sanitized = $settings->sanitize_api_key( $raw );

        $this->assertSame( 'test-key', $sanitized );
    }
}
