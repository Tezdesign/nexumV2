<?php
$updates = [
    'src/Entity/FinancialAnalysis/Transaction.php' => [
        '#[ORM\Index(name: "reference", columns: ["reference"])]'
    ],
    'src/Entity/Projects/Project.php' => [
        '#[ORM\Index(name: "idx_projects_assigned_to", columns: ["assigned_to"])]',
        '#[ORM\Index(name: "idx_projects_created_by", columns: ["created_by"])]',
        '#[ORM\Index(name: "idx_projects_end_date", columns: ["end_date"])]',
        '#[ORM\Index(name: "idx_projects_start_date", columns: ["start_date"])]'
    ],
    'src/Entity/ResourcesManagement/Resource.php' => [
        '#[ORM\UniqueConstraint(name: "resource_code", columns: ["resource_code"])]'
    ],
    'src/Entity/ResourcesManagement/ResourceAssignment.php' => [
        '#[ORM\Index(name: "fk_ra_resource", columns: ["resource_id"])]'
    ],
    'src/Entity/Tasks/Task.php' => [
        '#[ORM\Index(name: "fk_tasks_created_by", columns: ["created_by"])]',
        '#[ORM\Index(name: "idx_tasks_assigned_to", columns: ["assigned_to"])]',
        '#[ORM\Index(name: "idx_tasks_due_date", columns: ["due_date"])]',
        '#[ORM\Index(name: "idx_tasks_priority", columns: ["priority"])]',
        '#[ORM\Index(name: "idx_tasks_project", columns: ["project_id"])]',
        '#[ORM\Index(name: "idx_tasks_status", columns: ["status"])]'
    ],
    'src/Entity/Training/Quiz.php' => [
        '#[ORM\Index(name: "formation_id", columns: ["formation_id"])]'
    ],
    'src/Entity/Training/Resultat.php' => [
        '#[ORM\Index(name: "formation_id", columns: ["formation_id"])]'
    ],
    'src/Entity/UserHandling/Reclamation.php' => [
        '#[ORM\Index(name: "fk_reclamation_user", columns: ["id_user"])]'
    ],
    'src/Entity/UserHandling/Utilisateur.php' => [
        '#[ORM\UniqueConstraint(name: "email", columns: ["email"])]'
    ],
    'src/Entity/FinancialAnalysis/BudgetProfile.php' => [
        '#[ORM\Index(name: "fiscal_year", columns: ["fiscal_year"])]'
    ],
    'src/Entity/Chat/ConversationParticipant.php' => [
        '#[ORM\Index(name: "idx_cp_conversation", columns: ["conversation_id"])]',
        '#[ORM\Index(name: "idx_cp_last_read", columns: ["last_read_message_id"])]',
        '#[ORM\Index(name: "idx_cp_nickname", columns: ["nickname"])]',
        '#[ORM\Index(name: "idx_cp_user_active", columns: ["user_id"])]'
    ],
    'src/Entity/Chat/Conversation.php' => [
        '#[ORM\Index(name: "idx_conversations_created_by", columns: ["created_by"])]',
        '#[ORM\Index(name: "idx_conversations_last", columns: ["last_message_id"])]',
        '#[ORM\UniqueConstraint(name: "uq_conversations_dm_key", columns: ["dm_key"])]'
    ],
    'src/Entity/Chat/MessageAttachment.php' => [
        '#[ORM\Index(name: "index_attachment_message", columns: ["message_id"])]'
    ],
    'src/Entity/Chat/Message.php' => [
        '#[ORM\Index(name: "idx_messages_conv_id", columns: ["conversation_id"])]',
        '#[ORM\Index(name: "idx_messages_conv_time", columns: ["conversation_id", "created_at"])]',
        '#[ORM\Index(name: "idx_messages_sender_time", columns: ["sender_id", "created_at"])]'
    ],
    'src/Entity/Training/Participer.php' => [
        '#[ORM\Index(name: "user_id", columns: ["user_id"])]'
    ],
    'src/Entity/FinancialAnalysis/ProjectBudget.php' => [
        '#[ORM\Index(name: "fk_pro_id", columns: ["projectId"])]'
    ]
];

foreach ($updates as $file => $attrs) {
    if (!file_exists($file)) {
        echo "Missing: $file\n";
        continue;
    }
    $content = file_get_contents($file);
    
    $replacement = implode("\n", $attrs) . "\nclass ";
    $content = preg_replace('/class /', $replacement, $content, 1);
    
    file_put_contents($file, $content);
}
echo "Indexes added.\n";
