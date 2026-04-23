<?php
require_once 'vendor/autoload.php';

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

// Test the AI service call directly
$pythonScriptPath = dirname(__DIR__) . '/src/aitools/userai/ai_audit_wrapper.py';
$testPrompt = "Analyze these specific audit metrics and provide data-driven insights: USER METRICS: - Total Users: 1250 - Active Users: 1180 (94.4% engagement) - User Engagement Rate: 94.4% RECLAMATION METRICS: - Total Reclamations: 320 - Resolved: 245 (76.6% resolution rate) - Pending: 28 (8.8% pending rate)";

echo "Testing AI service call...\n";
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
    }
    
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
?>
