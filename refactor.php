<?php

$map = [
    'BudgetProfile' => 'FinancialAnalysis',
    'Conversation' => 'Chat',
    'ConversationParticipant' => 'Chat',
    'Formation' => 'Training',
    'Message' => 'Chat',
    'MessageAttachment' => 'Chat',
    'Participer' => 'Training',
    'Project' => 'Projects',
    'ProjectAssignment' => 'Projects',
    'ProjectBudget' => 'FinancialAnalysis',
    'Quiz' => 'Training',
    'Resource' => 'ResourcesManagement',
    'ResourceAssignment' => 'ResourcesManagement',
    'Resultat' => 'Training',
    'Task' => 'Tasks',
    'Transaction' => 'FinancialAnalysis',
    'Utilisateur' => 'UserHandling',
];

$srcDir = __DIR__ . '/src';
$entityDir = $srcDir . '/Entity';
$repoDir = $srcDir . '/Repository';

// Process Entities
foreach ($map as $entity => $module) {
    $modEntityDir = $entityDir . '/' . $module;
    if (!is_dir($modEntityDir)) {
        mkdir($modEntityDir, 0777, true);
    }
    
    $file = $entityDir . '/' . $entity . '.php';
    if (file_exists($file)) {
        $content = file_get_contents($file);
        
        // Update namespace
        $content = preg_replace('/namespace App\\\\Entity;/', "namespace App\\Entity\\$module;", $content);
        
        // Replace repo use
        $content = preg_replace("/use App\\\\Repository\\\\{$entity}Repository;/", "use App\\Repository\\$module\\{$entity}Repository;", $content);
        
        // Handle relations cross-imports
        $imports = [];
        if ($entity === 'ResourceAssignment') {
            $imports[] = 'App\\Entity\\UserHandling\\Utilisateur';
        }
        if ($entity === 'Transaction') {
            $imports[] = 'App\\Entity\\FinancialAnalysis\\ProjectBudget';
        }
        if ($entity === 'ProjectBudget') {
            $imports[] = 'App\\Entity\\FinancialAnalysis\\Transaction';
        }
        if ($entity === 'Utilisateur') {
            $imports[] = 'App\\Entity\\ResourcesManagement\\ResourceAssignment';
        }
        
        if (!empty($imports)) {
            $importStr = '';
            foreach ($imports as $imp) {
                if (!str_contains($content, "use $imp;")) {
                    $importStr .= "use $imp;\n";
                }
            }
            if ($importStr) {
                $content = preg_replace('/(use Doctrine\\\\ORM\\\\Mapping as ORM;)/', "$1\n" . trim($importStr), $content);
            }
        }
        
        file_put_contents($modEntityDir . '/' . $entity . '.php', $content);
        unlink($file);
        echo "Moved Entity $entity to $module\n";
    }
}

// Process Repositories
foreach ($map as $entity => $module) {
    $modRepoDir = $repoDir . '/' . $module;
    if (!is_dir($modRepoDir)) {
        mkdir($modRepoDir, 0777, true);
    }
    
    $repoName = $entity . 'Repository';
    $file = $repoDir . '/' . $repoName . '.php';
    if (file_exists($file)) {
        $content = file_get_contents($file);
        
        // Update namespace
        $content = preg_replace('/namespace App\\\\Repository;/', "namespace App\\Repository\\$module;", $content);
        
        // Replace entity use
        $content = preg_replace("/use App\\\\Entity\\\\{$entity};/", "use App\\Entity\\$module\\{$entity};", $content);
        
        file_put_contents($modRepoDir . '/' . $repoName . '.php', $content);
        unlink($file);
        echo "Moved Repository $repoName to $module\n";
    }
}
echo "Done.\n";
