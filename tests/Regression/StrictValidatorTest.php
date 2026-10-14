<?php

namespace Tests\Regression;

use App\Support\StrictValidator;

class StrictValidatorTest extends IsolatedTestCase
{
    public function test_email_control_characters_are_rejected(): void
    {
        foreach (["person@example.com\r\nBcc: outsider@example.com", "person\n@example.com", "person\0@example.com", ['person@example.com']] as $address) {
            $validator = new StrictValidator($this->app['translator'], ['email' => $address], ['email' => 'required|email']);
            $this->assertTrue($validator->fails());
        }
    }

    public function test_normal_email_and_rfc_rules_still_work(): void
    {
        $validator = new StrictValidator($this->app['translator'], ['email' => 'operations+test@example.com'], ['email' => 'required|email:rfc']);
        $this->assertTrue($validator->passes());
    }
}
