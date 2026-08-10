# BoardPrep Project Map

> Generated from the actual working tree. This is a structural inventory, not a substitute for source documentation.

## Top-Level Structure

| Path | Role |
|---|---|
| `app/` | Application source: runtime code, domain logic, services, controllers, storage, views, and framework infrastructure. |
| `app/Controllers/` | HTTP/application entry controllers. |
| `app/Core/` | Application framework and runtime infrastructure. |
| `app/DTO/` | Data-transfer objects carrying structured application data. |
| `app/Repositories/` | Persistence/data-access layer. |
| `app/Services/` | Application/business services. |
| `app/Views/` | Presentation templates and view components. |
| `app/Domains/` | Domain-oriented application components. |
| `app/Storage/` | Storage implementations. |
| `routes/` | HTTP route definitions. |
| `tools/Doctor/` | Developer diagnostics, architecture checks, health analysis, and application simulation. |
| `tools/Doctor/Simulation/` | End-to-end application simulation framework and scenarios. |
| `tools/Tests/` | Developer/runtime test suites. |
| `database/` | Application data and persistence files. |
| `public/` | Public HTTP entry point. |
| `docs/` | Project documentation and architectural maps. |

## File Inventory

| File | Namespace | Classes / Interfaces / Traits |
|---|---|---|
| `app/Builders/Developer/DeveloperPageBuilder.php` | `App\Builders\Developer` | class DeveloperPageBuilder |
| `app/Builders/Developer/EntityCardBuilder.php` | `App\Builders\Developer` | class EntityCardBuilder |
| `app/Constants/Status.php` | `App\Constants` | class Status |
| `app/Contracts/StorageInterface.php` | `App\Contracts` | interface StorageInterface |
| `app/Controllers/BaseController.php` | `App\Controllers` | class BaseController |
| `app/Controllers/BaseDeveloperController.php` | `App\Controllers` | class BaseDeveloperController |
| `app/Controllers/BlueprintController.php` | `App\Controllers` | class BlueprintController |
| `app/Controllers/BlueprintHealthController.php` | `App\Controllers` | class BlueprintHealthController |
| `app/Controllers/BoardController.php` | `App\Controllers` | class BoardController |
| `app/Controllers/CoverageController.php` | `App\Controllers` | class CoverageController |
| `app/Controllers/DashboardController.php` | `App\Controllers` | class DashboardController |
| `app/Controllers/DeveloperToolsController.php` | `` | class DeveloperToolsController |
| `app/Controllers/DoctorApiController.php` | `App\Controllers` | class DoctorApiController |
| `app/Controllers/DoctorDashboardController.php` | `App\Controllers` | class DoctorDashboardController |
| `app/Controllers/DoctorRunController.php` | `App\Controllers` | class DoctorRunController |
| `app/Controllers/HomeController.php` | `App\Controllers` | class HomeController |
| `app/Controllers/LearningProfileController.php` | `App\Controllers` | class LearningProfileController |
| `app/Controllers/MetadataRepairController.php` | `App\Controllers` | class MetadataRepairController |
| `app/Controllers/ProgressController.php` | `App\Controllers` | class ProgressController |
| `app/Controllers/QuestionEditorController.php` | `App\Controllers` | class QuestionEditorController |
| `app/Controllers/QuestionExportController.php` | `` | class QuestionExportController |
| `app/Controllers/QuestionImportController.php` | `App\Controllers` | class QuestionImportController |
| `app/Controllers/QuestionInspectorController.php` | `App\Controllers` | class QuestionInspectorController |
| `app/Controllers/QuestionQualityController.php` | `App\Controllers` | class QuestionQualityController |
| `app/Controllers/QuizFlowController.php` | `App\Controllers` | class QuizFlowController |
| `app/Controllers/RecommendationController.php` | `` | class RecommendationController |
| `app/Controllers/SubjectController.php` | `App\Controllers` | class SubjectController |
| `app/Controllers/TaxonomyController.php` | `App\Controllers` | class TaxonomyController |
| `app/Core/App.php` | `App\Core` | class App |
| `app/Core/Autoloader.php` | `App\Core` | class Autoloader |
| `app/Core/Config.php` | `` | class Config |
| `app/Core/Container.php` | `App\Core` | class Container |
| `app/Core/Database.php` | `App\Core` | class Database |
| `app/Core/Env.php` | `` | class Env |
| `app/Core/ExceptionHandler.php` | `App\Core` | class ExceptionHandler |
| `app/Core/QuestionStorage.php` | `` | class QuestionStorage |
| `app/Core/Request.php` | `App\Core` | class Request |
| `app/Core/Response.php` | `App\Core` | class Response |
| `app/Core/Router.php` | `App\Core` | class Router |
| `app/Core/Storage.php` | `App\Core` | class Storage |
| `app/Core/Validator.php` | `` | class Validator |
| `app/Core/View.php` | `App\Core` | class View |
| `app/DTO/QuizSpecification.php` | `` | class QuizSpecification |
| `app/DTO/SelectionRequest.php` | `` | class SelectionRequest |
| `app/Domains/Question/Actions/CreateQuestion.php` | `App\Domains\Question\Actions` | class CreateQuestion |
| `app/Domains/Question/Actions/UpdateQuestion.php` | `App\Domains\Question\Actions` | class UpdateQuestion |
| `app/Exceptions/AppException.php` | `App\Exceptions` | class AppException |
| `app/Exceptions/NotFoundException.php` | `App\Exceptions` | class NotFoundException |
| `app/Exceptions/StorageException.php` | `App\Exceptions` | class StorageException |
| `app/Exceptions/ValidationException.php` | `App\Exceptions` | class ValidationException |
| `app/Foundation/Arr.php` | `App\Foundation` | class Arr |
| `app/Foundation/Collection.php` | `App\Foundation` | class Collection |
| `app/Foundation/Html.php` | `App\Foundation` | class Html |
| `app/Foundation/Str.php` | `App\Foundation` | class Str |
| `app/Models/Question.php` | `` | class Question |
| `app/Repositories/AttemptRepository.php` | `App\Repositories` | class AttemptRepository |
| `app/Repositories/BaseRepository.php` | `App\Repositories` | class BaseRepository |
| `app/Repositories/BlueprintRepository.php` | `App\Repositories` | class BlueprintRepository |
| `app/Repositories/BoardRepository.php` | `App\Repositories` | class BoardRepository |
| `app/Repositories/BoardSubjectRepository.php` | `App\Repositories` | class BoardSubjectRepository |
| `app/Repositories/ProgressRepository.php` | `App\Repositories` | class ProgressRepository |
| `app/Repositories/QuestionRepository.php` | `App\Repositories` | class QuestionRepository |
| `app/Repositories/StatusRepository.php` | `App\Repositories` | class StatusRepository |
| `app/Repositories/SubjectRepository.php` | `App\Repositories` | class SubjectRepository |
| `app/Services/AttemptService.php` | `App\Services` | class AttemptService |
| `app/Services/Blueprint/BlueprintService.php` | `App\Services\Blueprint` | class BlueprintService |
| `app/Services/BlueprintService.php` | `App\Services` | class BlueprintService |
| `app/Services/Board/BoardService.php` | `App\Services\Board` | class BoardService |
| `app/Services/Board/BoardViewService.php` | `App\Services\Board` | class BoardViewService |
| `app/Services/Developer/DeveloperComponentRenderer.php` | `App\Services\Developer` | class DeveloperComponentRenderer |
| `app/Services/Developer/DeveloperViewService.php` | `App\Services\Developer` | class DeveloperViewService |
| `app/Services/Developer/DoctorHistoryService.php` | `App\Services\Developer` | class DoctorHistoryService |
| `app/Services/Learning/LearningCoachService.php` | `App\Services\Learning` | class LearningCoachService |
| `app/Services/Learning/LearningHistoryService.php` | `App\Services\Learning` | class LearningHistoryService |
| `app/Services/Learning/LearningStatisticsService.php` | `App\Services\Learning` | class LearningStatisticsService |
| `app/Services/Learning/LearningTimelineService.php` | `` | class LearningTimelineService |
| `app/Services/Learning/MasteryService.php` | `` | class MasteryService |
| `app/Services/Learning/PerformanceAnalyticsService.php` | `App\Services\Learning` | class PerformanceAnalyticsService |
| `app/Services/Learning/RecommendationService.php` | `App\Services\Learning` | class RecommendationService |
| `app/Services/Learning/WeaknessService.php` | `App\Services\Learning` | class WeaknessService |
| `app/Services/Learning/WeaknessStorageService.php` | `App\Services\Learning` | class WeaknessStorageService |
| `app/Services/Profile/LearningProfileService.php` | `App\Services\Profile` | class LearningProfileService |
| `app/Services/Quality/Validators/ChoiceValidator.php` | `App\Services\Quality\Validators` | class ChoiceValidator |
| `app/Services/Quality/Validators/ContentValidator.php` | `App\Services\Quality\Validators` | class ContentValidator |
| `app/Services/Quality/Validators/DuplicateValidator.php` | `App\Services\Quality\Validators` | class DuplicateValidator |
| `app/Services/Quality/Validators/MetadataValidator.php` | `App\Services\Quality\Validators` | class MetadataValidator |
| `app/Services/Quality/Validators/QuestionValidator.php` | `` | class QuestionValidator |
| `app/Services/Quality/Validators/TaxonomyValidator.php` | `App\Services\Quality\Validators` | class TaxonomyValidator |
| `app/Services/Question/QuestionAuthoringService.php` | `App\Services\Question` | class QuestionAuthoringService |
| `app/Services/Question/QuestionDuplicateService.php` | `App\Services\Question` | class QuestionDuplicateService |
| `app/Services/Question/QuestionEditorService.php` | `App\Services\Question` | class QuestionEditorService |
| `app/Services/Question/QuestionExportService.php` | `App\Services\Question` | class QuestionExportService |
| `app/Services/Question/QuestionQualityService.php` | `App\Services\Question` | class QuestionQualityService |
| `app/Services/Question/QuestionQueryService.php` | `App\Services\Question` | class QuestionQueryService |
| `app/Services/Question/QuestionSearchService.php` | `App\Services\Question` | class QuestionSearchService |
| `app/Services/Question/QuestionService.php` | `App\Services\Question` | class QuestionService |
| `app/Services/Question/QuestionStatisticsService.php` | `App\Services\Question` | class QuestionStatisticsService |
| `app/Services/Question/QuestionViewService.php` | `App\Services\Question` | class QuestionViewService |
| `app/Services/Quiz/AdaptiveQuizService.php` | `` | class AdaptiveQuizService |
| `app/Services/Quiz/Allocation/RuntimeAllocationService.php` | `` | class RuntimeAllocationService |
| `app/Services/Quiz/Blueprint/BlueprintAllocationReconciler.php` | `` | class BlueprintAllocationReconciler |
| `app/Services/Quiz/Blueprint/BlueprintCoverageAnalyzer.php` | `` | class BlueprintCoverageAnalyzer |
| `app/Services/Quiz/Blueprint/BlueprintCoverageValidator.php` | `` | class BlueprintCoverageValidator |
| `app/Services/Quiz/Blueprint/BlueprintDifficultyAllocator.php` | `` | class BlueprintDifficultyAllocator |
| `app/Services/Quiz/Blueprint/BlueprintDistributionService.php` | `` | class BlueprintDistributionService |
| `app/Services/Quiz/Blueprint/BlueprintExecutionResult.php` | `` | class BlueprintExecutionResult |
| `app/Services/Quiz/Blueprint/BlueprintExecutor.php` | `` | class BlueprintExecutor |
| `app/Services/Quiz/Blueprint/BlueprintIntegrityValidator.php` | `` | class BlueprintIntegrityValidator |
| `app/Services/Quiz/Blueprint/BlueprintResolverService.php` | `` | class BlueprintResolverService |
| `app/Services/Quiz/Blueprint/RequestExecutionPlan.php` | `` | class RequestExecutionPlan |
| `app/Services/Quiz/Blueprint/RequestExecutionPlanService.php` | `` | class RequestExecutionPlanService |
| `app/Services/Quiz/Blueprint/RequestPriorityService.php` | `` | class RequestPriorityService |
| `app/Services/Quiz/Blueprint/Resolution/BoardBlueprintResolver.php` | `` | class BoardBlueprintResolver |
| `app/Services/Quiz/Blueprint/Resolution/SubjectBlueprintResolver.php` | `` | class SubjectBlueprintResolver |
| `app/Services/Quiz/BlueprintResolverService.php` | `` | class BlueprintResolverService |
| `app/Services/Quiz/Board/BoardBlueprintResolver.php` | `` | class BoardBlueprintResolver |
| `app/Services/Quiz/Board/SubjectAllocationService.php` | `` | class SubjectAllocationService |
| `app/Services/Quiz/Coverage/CoverageTracker.php` | `` | class CoverageTracker |
| `app/Services/Quiz/ExamAssemblyService.php` | `` | class ExamAssemblyService |
| `app/Services/Quiz/QuestionBalancingService.php` | `` | class QuestionBalancingService |
| `app/Services/Quiz/QuestionSelectionService.php` | `` | class QuestionSelectionService |
| `app/Services/Quiz/QuizBlueprintService.php` | `` | class QuizBlueprintService |
| `app/Services/Quiz/QuizGenerationService.php` | `` | class QuizGenerationService |
| `app/Services/Quiz/QuizHistoryService.php` | `` | class QuizHistoryService |
| `app/Services/Quiz/QuizNavigationService.php` | `` | class QuizNavigationService |
| `app/Services/Quiz/QuizResultService.php` | `` | class QuizResultService |
| `app/Services/Quiz/QuizScoringService.php` | `` | class QuizScoringService |
| `app/Services/Quiz/QuizStartService.php` | `` | class QuizStartService |
| `app/Services/Quiz/QuizSubmissionService.php` | `` | class QuizSubmissionService |
| `app/Services/Quiz/Recovery/RecoveryCandidateService.php` | `` | class RecoveryCandidateService |
| `app/Services/Quiz/Recovery/RecoveryScope.php` | `` | - |
| `app/Services/Quiz/Selection/DifficultySelectionService.php` | `` | class DifficultySelectionService |
| `app/Services/Quiz/Selection/SelectionDeduplicator.php` | `` | class SelectionDeduplicator |
| `app/Services/Quiz/Selection/SelectionPool.php` | `` | class SelectionPool |
| `app/Services/Quiz/Selection/SelectionRequest.php` | `` | class SelectionRequest |
| `app/Services/Quiz/Selection/SelectionResult.php` | `` | class SelectionResult |
| `app/Services/Quiz/Selection/SelectionSession.php` | `` | class SelectionSession |
| `app/Services/Quiz/Selection/WeightedShuffleService.php` | `` | class WeightedShuffleService |
| `app/Services/Quiz/ShortageRecoveryService.php` | `` | class ShortageRecoveryService |
| `app/Services/Quiz/Specification/BaseSpecificationFactory.php` | `` | class BaseSpecificationFactory |
| `app/Services/Quiz/Subject/SubjectAssemblyService.php` | `` | class SubjectAssemblyService |
| `app/Services/Quiz/Subject/SubjectBlueprintResolver.php` | `` | class SubjectBlueprintResolver |
| `app/Services/Quiz/Validation/BlueprintQuotaValidator.php` | `` | class BlueprintQuotaValidator |
| `app/Services/QuizService.php` | `App\Services` | class QuizService |
| `app/Services/RepositoryHealth/Contracts/ValidatorInterface.php` | `` | interface ValidatorInterface |
| `app/Services/RepositoryHealth/DTO/HealthIssue.php` | `App\Services\RepositoryHealth\DTO` | class HealthIssue |
| `app/Services/RepositoryHealth/DTO/HealthReport.php` | `App\Services\RepositoryHealth\DTO` | class HealthReport |
| `app/Services/RepositoryHealth/DTO/RepositoryContext.php` | `App\Services\RepositoryHealth\DTO` | class RepositoryContext |
| `app/Services/RepositoryHealth/DTO/RepositoryStatistics.php` | `App\Services\RepositoryHealth\DTO` | class RepositoryStatistics |
| `app/Services/RepositoryHealth/DTO/ValidationResult.php` | `App\Services\RepositoryHealth\DTO` | class ValidationResult |
| `app/Services/RepositoryHealth/Engine/HealthScoreCalculator.php` | `App\Services\RepositoryHealth\Engine` | class HealthScoreCalculator |
| `app/Services/RepositoryHealth/Engine/QuestionIssueMapper.php` | `App\Services\RepositoryHealth\Engine` | class QuestionIssueMapper |
| `app/Services/RepositoryHealth/Engine/ReportBuilder.php` | `App\Services\RepositoryHealth\Engine` | class ReportBuilder |
| `app/Services/RepositoryHealth/Engine/RepositoryContextFactory.php` | `App\Services\RepositoryHealth\Engine` | class RepositoryContextFactory |
| `app/Services/RepositoryHealth/Engine/RepositoryHealthEngine.php` | `App\Services\RepositoryHealth\Engine` | class RepositoryHealthEngine |
| `app/Services/RepositoryHealth/Engine/RepositoryIssueMapper.php` | `App\Services\RepositoryHealth\Engine` | class RepositoryIssueMapper |
| `app/Services/RepositoryHealth/Engine/StatisticsBuilder.php` | `App\Services\RepositoryHealth\Engine` | class StatisticsBuilder |
| `app/Services/RepositoryHealth/Engine/ValidatorRegistry.php` | `App\Services\RepositoryHealth\Engine` | class ValidatorRegistry |
| `app/Services/Shared/BlueprintValidator.php` | `App\Services\Shared` | class BlueprintValidator |
| `app/Services/Shared/BoardValidator.php` | `App\Services\Shared` | class BoardValidator |
| `app/Services/Shared/CoverageMatrixService.php` | `` | class CoverageMatrixService |
| `app/Services/Shared/CoverageSummaryService.php` | `` | class CoverageSummaryService |
| `app/Services/Shared/DuplicateQuestionSummaryService.php` | `` | class DuplicateQuestionSummaryService |
| `app/Services/Shared/Import/QuestionImportProcessor.php` | `` | class QuestionImportProcessor |
| `app/Services/Shared/Import/QuestionImportReport.php` | `` | class QuestionImportReport |
| `app/Services/Shared/MetadataRepairProcessor.php` | `` | class MetadataRepairProcessor |
| `app/Services/Shared/MetadataRepairReport.php` | `` | class MetadataRepairReport |
| `app/Services/Shared/MetadataRepairService.php` | `` | class MetadataRepairService |
| `app/Services/Shared/QuestionAuditService.php` | `` | class QuestionAuditService |
| `app/Services/Shared/QuestionImportParser.php` | `` | class QuestionImportParser |
| `app/Services/Shared/QuestionImportProcessor.php` | `` | class QuestionImportProcessor |
| `app/Services/Shared/QuestionImportReport.php` | `` | class QuestionImportReport |
| `app/Services/Shared/QuestionImportService.php` | `App\Services\Shared` | class QuestionImportService |
| `app/Services/Shared/QuestionQualitySummaryService.php` | `` | class QuestionQualitySummaryService |
| `app/Services/Shared/QuestionValidationService.php` | `` | class QuestionValidationService |
| `app/Services/Shared/SessionService.php` | `` | class SessionService |
| `app/Services/Shared/SubjectStatisticsService.php` | `` | class SubjectStatisticsService |
| `app/Services/Shared/TaxonomyIntegrityService.php` | `App\Services\Shared` | class TaxonomyIntegrityService |
| `app/Services/Shared/TaxonomyService.php` | `` | class TaxonomyService |
| `app/Services/Shared/TaxonomyStatisticsService.php` | `` | class TaxonomyStatisticsService |
| `app/Services/Shared/TaxonomyStorageService.php` | `App\Services\Shared` | class TaxonomyStorageService |
| `app/Services/Subject/SubjectService.php` | `App\Services\Subject` | class SubjectService |
| `app/Services/Subject/SubjectValidator.php` | `App\Services\Subject` | class SubjectValidator |
| `app/Services/Subject/SubjectViewService.php` | `App\Services\Subject` | class SubjectViewService |
| `app/Storage/JsonStorage.php` | `App\Storage` | class JsonStorage |
| `app/Storage/MysqlStorage.php` | `App\Storage` | class MysqlStorage |
| `app/Support/Clock.php` | `` | class Clock |
| `app/Support/EntityFactory.php` | `` | class EntityFactory |
| `app/Support/Slugger.php` | `` | class Slugger |
| `app/ViewModels/Developer/ActionBarViewModel.php` | `App\ViewModels\Developer` | class ActionBarViewModel |
| `app/ViewModels/Developer/EntityCardViewModel.php` | `App\ViewModels\Developer` | class EntityCardViewModel |
| `app/ViewModels/Developer/PageHeaderViewModel.php` | `App\ViewModels\Developer` | class PageHeaderViewModel |
| `app/ViewModels/Developer/SummaryViewModel.php` | `App\ViewModels\Developer` | class SummaryViewModel |
| `app/Views/dashboard/index.php` | `` | - |
| `app/Views/developer/blueprint-create.php` | `` | - |
| `app/Views/developer/blueprint-health.php` | `` | - |
| `app/Views/developer/blueprints.php` | `` | - |
| `app/Views/developer/boards/create.php` | `` | - |
| `app/Views/developer/boards/index.php` | `` | - |
| `app/Views/developer/boards/view.php` | `` | - |
| `app/Views/developer/components/action-bar.php` | `` | - |
| `app/Views/developer/components/empty-state.php` | `` | - |
| `app/Views/developer/components/entity-card.php` | `` | - |
| `app/Views/developer/components/page-header.php` | `` | - |
| `app/Views/developer/components/summary.php` | `` | - |
| `app/Views/developer/coverage.php` | `` | - |
| `app/Views/developer/dashboard.php` | `` | - |
| `app/Views/developer/doctor/index.php` | `` | - |
| `app/Views/developer/footer.php` | `` | - |
| `app/Views/developer/index.php` | `` | - |
| `app/Views/developer/metadata-repair.php` | `` | - |
| `app/Views/developer/question-import.php` | `` | - |
| `app/Views/developer/question-inspector-list.php` | `` | - |
| `app/Views/developer/question-inspector.php` | `` | - |
| `app/Views/developer/question-quality.php` | `` | - |
| `app/Views/developer/question/editor.php` | `` | - |
| `app/Views/developer/question/form.php` | `` | - |
| `app/Views/developer/question/partials/actions.php` | `` | - |
| `app/Views/developer/question/partials/duplicates.php` | `` | - |
| `app/Views/developer/question/partials/options.php` | `` | - |
| `app/Views/developer/question/partials/question.php` | `` | - |
| `app/Views/developer/question/partials/taxonomy.php` | `` | - |
| `app/Views/developer/question/workspace.php` | `` | - |
| `app/Views/developer/sidebar.php` | `` | - |
| `app/Views/developer/subjects/create.php` | `` | - |
| `app/Views/developer/subjects/edit.php` | `` | - |
| `app/Views/developer/subjects/index.php` | `` | - |
| `app/Views/developer/subjects/view.php` | `` | - |
| `app/Views/developer/taxonomy.php` | `` | - |
| `app/Views/developer/topbar.php` | `` | - |
| `app/Views/developer/workspace/index.php` | `` | - |
| `app/Views/developer/workspace/partials/header.php` | `` | - |
| `app/Views/developer/workspace/partials/sidebar.php` | `` | - |
| `app/Views/english/index.php` | `` | - |
| `app/Views/grammar/index.php` | `` | - |
| `app/Views/home/index.php` | `` | - |
| `app/Views/layouts/developer.php` | `` | - |
| `app/Views/layouts/main.php` | `` | - |
| `app/Views/let/index.php` | `` | - |
| `app/Views/profile/index.php` | `` | - |
| `app/Views/progress/index.php` | `` | - |
| `app/Views/quiz/index.php` | `` | - |
| `app/Views/quiz/result.php` | `` | - |
| `app/Views/quiz/settings.php` | `` | - |
| `bootstrap/app.php` | `` | - |
| `config/app.php` | `` | - |
| `config/database.php` | `` | - |
| `database/modules/let/english/grammar/concepts.php` | `` | - |
| `database/questions/let/english/grammar/questions.php` | `` | class achieved |
| `public/api/doctor.php` | `` | - |
| `public/index.php` | `` | - |
| `routes/web.php` | `` | - |
| `tests/function-test.php` | `` | class unavailable, class unavailable, class unavailable |
| `tools/Doctor/Advisor/EffortAdvisor.php` | `Tools\Doctor\Advisor` | class EffortAdvisor |
| `tools/Doctor/Advisor/FixRecommendationAdvisor.php` | `Tools\Doctor\Advisor` | class FixRecommendationAdvisor |
| `tools/Doctor/Advisor/PriorityAdvisor.php` | `Tools\Doctor\Advisor` | class PriorityAdvisor |
| `tools/Doctor/Advisor/QuickWinAdvisor.php` | `Tools\Doctor\Advisor` | class QuickWinAdvisor |
| `tools/Doctor/Advisor/RecommendationAdvisor.php` | `Tools\Doctor\Advisor` | class RecommendationAdvisor |
| `tools/Doctor/Analyzers/CyclomaticAnalyzer.php` | `Tools\Doctor\Analyzers` | class CyclomaticAnalyzer |
| `tools/Doctor/Analyzers/DependencyRiskAnalyzer.php` | `Tools\Doctor\Analyzers` | class DependencyRiskAnalyzer |
| `tools/Doctor/Analyzers/GraphStatisticsAnalyzer.php` | `Tools\Doctor\Analyzers` | class GraphStatisticsAnalyzer |
| `tools/Doctor/Architecture/ArchitectureDecisionRecorder.php` | `Tools\Doctor\Architecture` | class ArchitectureDecisionRecorder |
| `tools/Doctor/Baseline/BaselineManager.php` | `Tools\Doctor\Baseline` | class BaselineManager |
| `tools/Doctor/Baseline/BaselineRepository.php` | `Tools\Doctor\Baseline` | class BaselineRepository |
| `tools/Doctor/Checks/ArchitectureScorecardCheck.php` | `Tools\Doctor\Checks` | class ArchitectureScorecardCheck |
| `tools/Doctor/Checks/ChangeImpactCheck.php` | `Tools\Doctor\Checks` | class ChangeImpactCheck |
| `tools/Doctor/Checks/CircularDependencyCheck.php` | `Tools\Doctor\Checks` | class CircularDependencyCheck |
| `tools/Doctor/Checks/ControllerComplexityCheck.php` | `Tools\Doctor\Checks` | class ControllerComplexityCheck |
| `tools/Doctor/Checks/CyclomaticComplexityCheck.php` | `Tools\Doctor\Checks` | class CyclomaticComplexityCheck |
| `tools/Doctor/Checks/DeadClassCheck.php` | `Tools\Doctor\Checks` | class DeadClassCheck |
| `tools/Doctor/Checks/DependencyCouplingCheck.php` | `Tools\Doctor\Checks` | class DependencyCouplingCheck |
| `tools/Doctor/Checks/DomainMigrationCheck.php` | `Tools\Doctor\Checks` | class DomainMigrationCheck |
| `tools/Doctor/Checks/EmptyDirectoryCheck.php` | `Tools\Doctor\Checks` | class EmptyDirectoryCheck |
| `tools/Doctor/Checks/FoundationUsageCheck.php` | `Tools\Doctor\Checks` | class FoundationUsageCheck |
| `tools/Doctor/Checks/HistoryTrendCheck.php` | `Tools\Doctor\Checks` | class HistoryTrendCheck |
| `tools/Doctor/Checks/HotspotCheck.php` | `Tools\Doctor\Checks` | class HotspotCheck |
| `tools/Doctor/Checks/LargestControllerCheck.php` | `Tools\Doctor\Checks` | class LargestControllerCheck |
| `tools/Doctor/Checks/LargestMethodCheck.php` | `Tools\Doctor\Checks` | class LargestMethodCheck |
| `tools/Doctor/Checks/LargestServiceCheck.php` | `Tools\Doctor\Checks` | class LargestServiceCheck |
| `tools/Doctor/Checks/LayerViolationCheck.php` | `Tools\Doctor\Checks` | class LayerViolationCheck |
| `tools/Doctor/Checks/LegacyFileCheck.php` | `Tools\Doctor\Checks` | class LegacyFileCheck |
| `tools/Doctor/Checks/LongParameterListCheck.php` | `Tools\Doctor\Checks` | class LongParameterListCheck |
| `tools/Doctor/Checks/MaintainabilityCheck.php` | `Tools\Doctor\Checks` | class MaintainabilityCheck |
| `tools/Doctor/Checks/ProjectStatisticsCheck.php` | `Tools\Doctor\Checks` | class ProjectStatisticsCheck |
| `tools/Doctor/Checks/QualityGateCheck.php` | `Tools\Doctor\Checks` | class QualityGateCheck |
| `tools/Doctor/Checks/RefactorCandidateCheck.php` | `Tools\Doctor\Checks` | class RefactorCandidateCheck |
| `tools/Doctor/Checks/RegressionCheck.php` | `Tools\Doctor\Checks` | class RegressionCheck |
| `tools/Doctor/Checks/RiskScoreCheck.php` | `Tools\Doctor\Checks` | class RiskScoreCheck |
| `tools/Doctor/Checks/RuntimeFunctionTestCheck.php` | `Tools\Doctor\Checks` | class RuntimeFunctionTestCheck |
| `tools/Doctor/Checks/TechnicalDebtBreakdownCheck.php` | `Tools\Doctor\Checks` | class TechnicalDebtBreakdownCheck |
| `tools/Doctor/Checks/TechnicalDebtCheck.php` | `Tools\Doctor\Checks` | class TechnicalDebtCheck |
| `tools/Doctor/Checks/TodoCommentCheck.php` | `Tools\Doctor\Checks` | class TodoCommentCheck |
| `tools/Doctor/Checks/UnusedImportCheck.php` | `Tools\Doctor\Checks` | class UnusedImportCheck |
| `tools/Doctor/Commands/BaselineCommand.php` | `Tools\Doctor\Commands` | class BaselineCommand |
| `tools/Doctor/Config/DoctorConfig.php` | `Tools\Doctor\Config` | class DoctorConfig |
| `tools/Doctor/Context/DoctorContext.php` | `Tools\Doctor\Context` | class DoctorContext |
| `tools/Doctor/Context/MetricsContext.php` | `Tools\Doctor\Context` | class MetricsContext |
| `tools/Doctor/Contracts/CheckInterface.php` | `Tools\Doctor\Contracts` | interface CheckInterface |
| `tools/Doctor/DTO/CheckResult.php` | `Tools\Doctor\DTO` | class CheckResult |
| `tools/Doctor/DTO/DoctorResult.php` | `Tools\Doctor\DTO` | class DoctorResult |
| `tools/Doctor/DTO/Issue.php` | `Tools\Doctor\DTO` | class Issue |
| `tools/Doctor/Engine/AnalyzerRegistry.php` | `Tools\Doctor\Engine` | class AnalyzerRegistry |
| `tools/Doctor/Engine/Doctor.php` | `Tools\Doctor\Engine` | class Doctor |
| `tools/Doctor/Engine/DoctorRunner.php` | `Tools\Doctor\Engine` | class DoctorRunner |
| `tools/Doctor/Engine/FileCounter.php` | `Tools\Doctor\Engine` | class FileCounter |
| `tools/Doctor/Graph/KnowledgeGraph.php` | `Tools\Doctor\Graph` | class KnowledgeGraph |
| `tools/Doctor/Graph/KnowledgeGraphBuilder.php` | `Tools\Doctor\Graph` | class KnowledgeGraphBuilder |
| `tools/Doctor/History/HistoryRepository.php` | `Tools\Doctor\History` | class HistoryRepository |
| `tools/Doctor/History/TrendAnalyzer.php` | `Tools\Doctor\History` | class TrendAnalyzer |
| `tools/Doctor/Issues/Issue.php` | `Tools\Doctor\Issues` | class Issue |
| `tools/Doctor/Metrics/CyclomaticAnalyzer.php` | `Tools\Doctor\Metrics` | class CyclomaticAnalyzer |
| `tools/Doctor/Metrics/MaintainabilityAnalyzer.php` | `Tools\Doctor\Metrics` | class MaintainabilityAnalyzer |
| `tools/Doctor/Metrics/MetricRegistry.php` | `Tools\Doctor\Metrics` | class MetricRegistry |
| `tools/Doctor/Metrics/MetricsPipeline.php` | `Tools\Doctor\Metrics` | class MetricsPipeline |
| `tools/Doctor/Output/ConsoleRenderer.php` | `Tools\Doctor\Output` | class ConsoleRenderer |
| `tools/Doctor/Output/JsonRenderer.php` | `Tools\Doctor\Output` | class JsonRenderer |
| `tools/Doctor/Output/JsonReportWriter.php` | `Tools\Doctor\Output` | class JsonReportWriter |
| `tools/Doctor/Output/MarkdownRenderer.php` | `Tools\Doctor\Output` | class MarkdownRenderer |
| `tools/Doctor/Registry/AnalyzerRegistry.php` | `Tools\Doctor\Registry` | class AnalyzerRegistry |
| `tools/Doctor/Registry/CheckRegistry.php` | `Tools\Doctor\Registry` | class CheckRegistry |
| `tools/Doctor/Registry/DoctorManifest.php` | `Tools\Doctor\Registry` | class DoctorManifest |
| `tools/Doctor/Rules/Rules.php` | `Tools\Doctor\Rules` | class Rules |
| `tools/Doctor/Scanners/DependencyScanner.php` | `Tools\Doctor\Scanners` | class DependencyScanner |
| `tools/Doctor/Scanners/EntryPointScanner.php` | `Tools\Doctor\Scanners` | class EntryPointScanner |
| `tools/Doctor/Scanners/FileScanner.php` | `Tools\Doctor\Scanners` | class FileScanner |
| `tools/Doctor/Scanners/MethodBodyScanner.php` | `Tools\Doctor\Scanners` | class MethodBodyScanner |
| `tools/Doctor/Scanners/PhpSourceScanner.php` | `Tools\Doctor\Scanners` | class PhpSourceScanner |
| `tools/Doctor/Scanners/TokenScanner.php` | `Tools\Doctor\Scanners` | class TokenScanner |
| `tools/Doctor/Simulation/ApplicationSimulator.php` | `Tools\Doctor\Simulation` | class ApplicationSimulator |
| `tools/Doctor/Simulation/Assertions/SimulationAssertions.php` | `Tools\Doctor\Simulation\Assertions` | class SimulationAssertions |
| `tools/Doctor/Simulation/HttpSimulator.php` | `Tools\Doctor\Simulation` | class HttpSimulator |
| `tools/Doctor/Simulation/Output/SimulationReport.php` | `Tools\Doctor\Simulation\Output` | class SimulationReport |
| `tools/Doctor/Simulation/Registry/DefaultScenarioRegistry.php` | `Tools\Doctor\Simulation\Registry` | class DefaultScenarioRegistry |
| `tools/Doctor/Simulation/Registry/ScenarioRegistry.php` | `Tools\Doctor\Simulation\Registry` | class ScenarioRegistry |
| `tools/Doctor/Simulation/Runner/SimulationRunner.php` | `Tools\Doctor\Simulation\Runner` | class SimulationRunner |
| `tools/Doctor/Simulation/Scenarios/ApplicationSmokeScenario.php` | `Tools\Doctor\Simulation\Scenarios` | class ApplicationSmokeScenario |
| `tools/Doctor/Simulation/Scenarios/HomePageScenario.php` | `Tools\Doctor\Simulation\Scenarios` | class HomePageScenario |
| `tools/Doctor/Simulation/Scenarios/HttpStatusScenario.php` | `Tools\Doctor\Simulation\Scenarios` | class HttpStatusScenario |
| `tools/Doctor/Simulation/Scenarios/LearningSurfaceScenario.php` | `Tools\Doctor\Simulation\Scenarios` | class LearningSurfaceScenario |
| `tools/Doctor/Simulation/Scenarios/QuestionEditorScenario.php` | `Tools\Doctor\Simulation\Scenarios` | class QuestionEditorScenario |
| `tools/Doctor/Simulation/Scenarios/QuizLifecycleScenario.php` | `Tools\Doctor\Simulation\Scenarios` | class QuizLifecycleScenario |
| `tools/Doctor/Simulation/SimulationCommand.php` | `Tools\Doctor\Simulation` | class SimulationCommand |
| `tools/Doctor/Simulation/SimulationContext.php` | `Tools\Doctor\Simulation` | class SimulationContext |
| `tools/Doctor/Simulation/SimulationResponse.php` | `Tools\Doctor\Simulation` | class SimulationResponse |
| `tools/Doctor/Simulation/SimulationResult.php` | `Tools\Doctor\Simulation` | class SimulationResult |
| `tools/Doctor/Simulation/SimulationScenario.php` | `Tools\Doctor\Simulation` | class SimulationScenario |
| `tools/Doctor/Simulation/SimulationSuite.php` | `Tools\Doctor\Simulation` | class SimulationSuite |
| `tools/Doctor/Snapshot/ProjectSnapshot.php` | `Tools\Doctor\Snapshot` | class ProjectSnapshot |
| `tools/Doctor/Snapshot/ProjectSnapshotBuilder.php` | `Tools\Doctor\Snapshot` | class ProjectSnapshotBuilder |
| `tools/Tests/FunctionTest.php` | `Tools\Tests` | class FunctionTest, class MemoryStorageTest |
| `tools/Tests/HttpTest.php` | `Tools\Tests` | class HttpTest |
| `tools/Tests/MemoryStorage.php` | `Tools\Tests` | class MemoryStorage |
| `tools/Tests/QuizTest.php` | `Tools\Tests` | class QuizTest |
| `tools/Tests/RepositoryTest.php` | `Tools\Tests` | class RepositoryTest, class exists, class exists |
| `tools/Tests/ServiceTest.php` | `Tools\Tests` | class ServiceTest, class exists |
| `tools/Tests/Simulation/SimulationKernelTest.php` | `Tools\Tests\Simulation` | class SimulationKernelTest |
| `tools/Tests/Simulation/run.php` | `` | - |
| `tools/Tests/TestCase.php` | `Tools\Tests` | class TestCase |
| `tools/bootstrap.php` | `` | - |
| `tools/doctor.php` | `` | - |
| `tools/function-test.php` | `` | - |
| `tools/http-test.php` | `` | - |
| `tools/release.php` | `` | - |
| `tools/stats.php` | `` | - |
| `tools/verify.php` | `` | - |

## Semantic Purpose Notes

Purpose should be documented at module/file level as we inspect the architecture. Do not infer business behavior solely from filenames.

### Quiz Pipeline
- `app/Services/Quiz/` — quiz generation, selection, scoring, navigation, submission, results, blueprints, and related quiz mechanics.
- `app/Services/Quiz/Blueprint/` — blueprint resolution, validation, execution, allocation, and coverage concerns.
- `app/Services/Quiz/Selection/` — question selection and selection-session mechanics.
- `tools/Doctor/Simulation/` — realistic HTTP/application behavior verification.

## Known Verification Entry Points

- Detailed application simulation: `tools/Doctor/Simulation/SimulationCommand.php`
- Simulation suite: `tools/Doctor/Simulation/SimulationSuite.php`
- Simulation runner: `tools/Doctor/Simulation/Runner/SimulationRunner.php`
- Lightweight simulation kernel: `tools/Tests/Simulation/run.php`
- Full Doctor entry point: `tools/doctor.php`
