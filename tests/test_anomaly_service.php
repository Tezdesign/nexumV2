<?php
require_once 'vendor/autoload.php';

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

// Test the anomaly detection AI service call directly
$pythonScriptPath = dirname(__DIR__) . '/src/aitools/userai/ai_audit_wrapper.py';
$testPrompt = "Analyze this system data for anomalies and security issues: user_activity: Normal patterns with slight increase in logins performance: Response times within acceptable ranges error_rates: Error rate at 0.8%, slightly above baseline login_patterns: Multiple login attempts detected from unusual locations";

echo "Testing anomaly detection AI service call...\n";
echo "Prompt: " . $testPrompt . "\n\n";

try {
    $process = new Process(['python', $pythonScriptPath]);
    $process->setInput($testPrompt);
    $process->setTimeout(30);
    
    echo "Running process...\n";
    $process->run();
    
    if (!$process->isSuccessful()) {
        echo "Process failed!\n";
        echo "Error output: " . $process->getErrorOutput() . "\n";
        echo "Exit code: " . $process->getExitCode() . "\n";
        throw new ProcessFailedException($process);
    }
    
    $output = $process->getOutput();
    echo "Raw output: " . $output . "\n\n";
    
    // Test JSON parsing
    $data = json_decode($output, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "JSON decode error: " . json_last_error_msg() . "\n";
    } else {
        echo "Parsed JSON data:\n";
        print_r($data);
        
        echo "\nChecking anomaly data:\n";
        echo "Anomalies count: " . (isset($data['anomalies']) ? count($data['anomalies']) : 0) . "\n";
        echo "Security concerns count: " . (isset($data['security_concerns']) ? count($data['security_concerns']) : 0) . "\n";
        echo "Performance issues count: " . (isset($data['performance_issues']) ? count($data['performance_issues']) : 0) . "\n";
        echo "Recommendations count: " . (isset($data['recommendations']) ? count($data['recommendations']) : 0) . "\n";
    }
    
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
?>
