<?php

namespace App\Service\FinancialAnalysis;

use App\Entity\FinancialAnalysis\ExpenseDraft;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class DraftPolicyService
{
    public function __construct(
        private HttpClientInterface $httpClient
    ) {}

    public function evaluatePolicy(ExpenseDraft $draft): array
    {
        // Define the hardcoded company policy text
        $companyPolicy = <<<TEXT
1. Software subscriptions exceeding $200 require explicit CTO authorization.
2. Travel expenses are restricted to Economy Class only; First or Business class is strictly prohibited.
3. Office Supplies exceeding $50 per item must be justified with a team-wide benefit description.
4. Any expense for "Entertainment" or "Gifts" must include the recipient's name and relationship to the company.
5. Marketing spend above $1000 must be linked to an approved quarterly campaign ID.
6. Professionalism: All expenses must have a serious, professional business justification. Absurd, silly, or unreasonable expenses (e.g., "money for beer to celebrate") are strictly prohibited.

CRITICAL INSTRUCTION FOR REASONS:
If you REJECT a draft because it violates BOTH the Professionalism rule (Rule 6) AND at least one other standard policy (Rules 1-5), your "reason" field MUST exactly match this format: "this violates [N] policies and it is unprofessional" (where [N] is the total number of rules violated). Do not add any other text to the reason in this specific case.
TEXT;

        $payload = [
            'draft_id' => $draft->getId(),
            'amount' => (float) $draft->getAmount(),
            'category' => $draft->getCategory(),
            'description' => $draft->getDescription(),
            'company_policy' => $companyPolicy
        ];

        try {
            $apiUrl = $_ENV['AI_API_URL'] ?? 'http://127.0.0.1:5000';
            $response = $this->httpClient->request('POST', rtrim($apiUrl, '/') . '/api/nexum/evaluate', [
                'json' => $payload,
                'timeout' => 180,
            ]);
            return $response->toArray(false);
        } catch (\Exception $e) {
            return [
                "status" => "ERROR",
                "decision" => "FLAG_FOR_HUMAN",
                "reason" => "Nexum Engine unavailable: " . $e->getMessage()
            ];
        }
    }
}