<?php

namespace Drupal\xb_ai\Plugin\AiFunctionCall;

use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\ai_agents\PluginInterfaces\AiAgentContextInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Plugin implementation to detect if a request requires complex planning.
 */
#[FunctionCall(
  id: 'ai_agent:detect_complex_request',
  function_name: 'ai_agent_detect_complex_request',
  name: 'Detect Complex Request',
  description: 'Analyzes user request to determine if it requires structured planning for landing page creation.',
  group: 'planning_tools',
  module_dependencies: ['experience_builder'],
  context_definitions: [
    'user_request' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("User Request"),
      description: new TranslatableMarkup("The user's original request to analyze for complexity."),
      required: TRUE
    ),
  ],
)]
final class DetectComplexRequest extends FunctionCallBase implements ExecutableFunctionCallInterface, AiAgentContextInterface {

  /**
   * The analysis result.
   *
   * @var array
   */
  protected array $analysisResult = [];

  /**
   * Complex request indicators - keywords that suggest landing page creation.
   *
   * @var array
   */
  private const COMPLEX_KEYWORDS = [
    'landing page',
    'full page',
    'website',
    'homepage',
    'home page',
    'site',
    'complete page',
    'entire page',
    'web page',
    'page design',
    'page layout',
    'multi-section',
    'multiple sections',
    'full site',
    'whole page',
  ];

  /**
   * Multi-component indicators - phrases suggesting multiple components.
   *
   * @var array
   */
  private const MULTI_COMPONENT_INDICATORS = [
    'hero and',
    'header and',
    'footer and',
    'testimonial',
    'contact form',
    'feature grid',
    'gallery',
    'carousel',
    'pricing',
    'team section',
    'about section',
    'services section',
    'portfolio',
    'blog section',
    'newsletter',
    'call to action',
    'cta',
    'sections',
    'multiple',
    'several',
    'various',
    'different',
  ];

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    $userRequest = strtolower($this->getContextValue('user_request'));
    
    $complexityScore = 0;
    $detectedKeywords = [];
    $detectedIndicators = [];
    
    // Check for complex keywords
    foreach (self::COMPLEX_KEYWORDS as $keyword) {
      if (strpos($userRequest, $keyword) !== FALSE) {
        $complexityScore += 10;
        $detectedKeywords[] = $keyword;
      }
    }
    
    // Check for multi-component indicators
    foreach (self::MULTI_COMPONENT_INDICATORS as $indicator) {
      if (strpos($userRequest, $indicator) !== FALSE) {
        $complexityScore += 5;
        $detectedIndicators[] = $indicator;
      }
    }
    
    // Additional complexity factors
    $wordCount = str_word_count($userRequest);
    if ($wordCount > 15) {
      $complexityScore += 3;
    }
    if ($wordCount > 25) {
      $complexityScore += 5;
    }
    
    // Check for connecting words that suggest multiple elements
    $connectingWords = ['with', 'and', 'plus', 'including', 'featuring', 'containing'];
    $connectingWordCount = 0;
    foreach ($connectingWords as $word) {
      $connectingWordCount += substr_count($userRequest, $word);
    }
    $complexityScore += $connectingWordCount * 2;
    
    // Determine complexity level
    $complexityLevel = 'low';
    $requiresPlanning = FALSE;
    
    if ($complexityScore >= 20) {
      $complexityLevel = 'high';
      $requiresPlanning = TRUE;
    } elseif ($complexityScore >= 10) {
      $complexityLevel = 'medium';
      $requiresPlanning = TRUE;
    }
    
    $this->analysisResult = [
      'requires_planning' => $requiresPlanning,
      'complexity_level' => $complexityLevel,
      'complexity_score' => $complexityScore,
      'detected_keywords' => $detectedKeywords,
      'detected_indicators' => $detectedIndicators,
      'word_count' => $wordCount,
      'connecting_words' => $connectingWordCount,
      'reasoning' => $this->generateReasoning($complexityScore, $detectedKeywords, $detectedIndicators),
    ];
  }

  /**
   * Generate reasoning for the complexity detection.
   *
   * @param int $score
   *   The complexity score.
   * @param array $keywords
   *   Detected complex keywords.
   * @param array $indicators
   *   Detected multi-component indicators.
   *
   * @return string
   *   Human-readable reasoning for the decision.
   */
  private function generateReasoning(int $score, array $keywords, array $indicators): string {
    $reasons = [];
    
    if (!empty($keywords)) {
      $reasons[] = "Contains landing page keywords: " . implode(', ', array_slice($keywords, 0, 3));
    }
    
    if (!empty($indicators)) {
      $reasons[] = "Mentions multiple components: " . implode(', ', array_slice($indicators, 0, 3));
    }
    
    if ($score >= 20) {
      $reasons[] = "High complexity score ({$score}) indicates multi-section landing page";
    } elseif ($score >= 10) {
      $reasons[] = "Medium complexity score ({$score}) suggests structured approach needed";
    } else {
      $reasons[] = "Low complexity score ({$score}) indicates simple component request";
    }
    
    return implode('. ', $reasons);
  }

  /**
   * {@inheritdoc}
   */
  public function getReadableOutput(): string {
    return Yaml::dump($this->analysisResult, 10, 2);
  }

}