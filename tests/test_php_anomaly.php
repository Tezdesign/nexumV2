<?php
require_once 'vendor/autoload.php';

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

// Test the exact anomaly detection prompt that PHP service uses
$pythonScriptPath = dirname(__DIR__) . '/src/aitools/userai/ai_audit_wrapper.py';

// Simulate the exact prompt from buildAnomalyDetectionPrompt
$systemData = [
    'user_activity' => 'Normal patterns with slight increase in logins',
    'performance' => 'Response times within acceptable ranges',
    'error_rates' => 'Error rate at 0.8%, slightly above baseline',
    'login_patterns' => 'Multiple login attempts detected from unusual locations'
];

$prompt = "As an AI security auditor, analyze the following system metrics for anomalies:

User Activity: {$systemData['user_activity']}
System Performance: {$systemData['performance']}
Error Rates: {$systemData['error_rates']}
Login Patterns: {$systemData['login_patterns']}

Please identify:
1. Unusual patterns
2. Security concerns
3. Performance issues
4. Recommended actions

Format your response as JSON with keys: anomalies, security_concerns, performance_issues, recommendations";

echo "Testing exact PHP anomaly detection prompt...\n";
echo "Prompt length: " . strlen($prompt) . " characters\n\n";

try {
    $process = new Process(['python', $pythonScriptPath]);
    $process->setInput($prompt);
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
        echo "SUCCESS: JSON parsed correctly!\n";
        echo "Anomalies: " . (isset($data['anomalies']) ? count($data['anomalies']) : 0) . "\n";
        echo "Security concerns: " . (isset($data['security_concerns']) ? count($data['security_concerns']) : 0) . "\n";
        echo "Performance issues: " . (isset($data['performance_issues']) ? count($data['performance_issues']) : 0) . "\n";
        echo "Recommendations: " . (isset($data['recommendations']) ? count($data['recommendations']) : 0) . "\n";
    }
    
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
?>
