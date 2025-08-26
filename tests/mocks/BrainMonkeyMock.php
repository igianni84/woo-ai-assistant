<?php
/**
 * Brain Monkey Mock for Tests
 * 
 * Simple mock implementation of Brain Monkey Functions\when for testing
 * without requiring the full Brain Monkey package.
 */

namespace Brain\Monkey\Functions {
    if (!function_exists('when')) {
        function when($function_name) {
            return new WhenMock($function_name);
        }
    }
    
    class WhenMock {
        private $function_name;
        
        public function __construct($function_name) {
            $this->function_name = $function_name;
        }
        
        public function justReturn($value = null) {
            // For tests, we don't need to actually mock the function
            // since we already have all required functions mocked
            return $this;
        }
    }
}