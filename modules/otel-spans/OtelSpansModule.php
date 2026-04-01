<?php
// SPDX-License-Identifier: AGPL-3.0-or-later
declare(strict_types=1);

namespace OtelSpans;

use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Validator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Baggage\Baggage;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;

class OtelSpansModule extends AbstractModule implements ModuleCustomInterface, MiddlewareInterface
{
    use ModuleCustomTrait;

    private const ROUTE_MAP = [
        // Kategorie 1: Record-Ansicht
        \Fisharebest\Webtrees\Http\RequestHandlers\IndividualPage::class
            => ['action' => 'view_individual', 'type' => 'query'],
        \Fisharebest\Webtrees\Http\RequestHandlers\FamilyPage::class
            => ['action' => 'view_family', 'type' => 'query'],
        \Fisharebest\Webtrees\Http\RequestHandlers\SourcePage::class
            => ['action' => 'view_source', 'type' => 'query'],
        \Fisharebest\Webtrees\Http\RequestHandlers\MediaPage::class
            => ['action' => 'view_media', 'type' => 'query'],
        \Fisharebest\Webtrees\Http\RequestHandlers\NotePage::class
            => ['action' => 'view_note', 'type' => 'query'],
        \Fisharebest\Webtrees\Http\RequestHandlers\SharedNotePage::class
            => ['action' => 'view_shared_note', 'type' => 'query'],
        \Fisharebest\Webtrees\Http\RequestHandlers\RepositoryPage::class
            => ['action' => 'view_repository', 'type' => 'query'],
        \Fisharebest\Webtrees\Http\RequestHandlers\LocationPage::class
            => ['action' => 'view_location', 'type' => 'query'],
        \Fisharebest\Webtrees\Http\RequestHandlers\SubmitterPage::class
            => ['action' => 'view_submitter', 'type' => 'query'],
        \Fisharebest\Webtrees\Http\RequestHandlers\SubmissionPage::class
            => ['action' => 'view_submission', 'type' => 'query'],
        \Fisharebest\Webtrees\Http\RequestHandlers\HeaderPage::class
            => ['action' => 'view_header', 'type' => 'query'],
        \Fisharebest\Webtrees\Http\RequestHandlers\GedcomRecordPage::class
            => ['action' => 'view_record', 'type' => 'query'],
        \Fisharebest\Webtrees\Http\RequestHandlers\TreePage::class
            => ['action' => 'view_tree', 'type' => 'query'],

        // Kategorie 2: Suche
        \Fisharebest\Webtrees\Http\RequestHandlers\SearchGeneralPage::class
            => ['action' => 'search_general', 'type' => 'query'],
        \Fisharebest\Webtrees\Http\RequestHandlers\SearchGeneralAction::class
            => ['action' => 'search_general', 'type' => 'query'],
        \Fisharebest\Webtrees\Http\RequestHandlers\SearchAdvancedPage::class
            => ['action' => 'search_advanced', 'type' => 'query'],
        \Fisharebest\Webtrees\Http\RequestHandlers\SearchAdvancedAction::class
            => ['action' => 'search_advanced', 'type' => 'query'],
        \Fisharebest\Webtrees\Http\RequestHandlers\SearchPhoneticPage::class
            => ['action' => 'search_phonetic', 'type' => 'query'],
        \Fisharebest\Webtrees\Http\RequestHandlers\SearchPhoneticAction::class
            => ['action' => 'search_phonetic', 'type' => 'query'],
        \Fisharebest\Webtrees\Http\RequestHandlers\SearchQuickAction::class
            => ['action' => 'search_quick', 'type' => 'query'],
        \Fisharebest\Webtrees\Http\RequestHandlers\SearchReplacePage::class
            => ['action' => 'search_replace_form', 'type' => 'edit'],

        // Kategorie 3: Bearbeitung
        \Fisharebest\Webtrees\Http\RequestHandlers\EditFactPage::class
            => ['action' => 'edit_fact_form', 'type' => 'edit'],
        \Fisharebest\Webtrees\Http\RequestHandlers\EditFactAction::class
            => ['action' => 'edit_fact_save', 'type' => 'edit'],
        \Fisharebest\Webtrees\Http\RequestHandlers\EditRecordPage::class
            => ['action' => 'edit_record_form', 'type' => 'edit'],
        \Fisharebest\Webtrees\Http\RequestHandlers\EditRecordAction::class
            => ['action' => 'edit_record_save', 'type' => 'edit'],
        \Fisharebest\Webtrees\Http\RequestHandlers\EditRawFactPage::class
            => ['action' => 'edit_raw_fact_form', 'type' => 'edit'],
        \Fisharebest\Webtrees\Http\RequestHandlers\EditRawFactAction::class
            => ['action' => 'edit_raw_fact_save', 'type' => 'edit'],
        \Fisharebest\Webtrees\Http\RequestHandlers\EditRawRecordPage::class
            => ['action' => 'edit_raw_record_form', 'type' => 'edit'],
        \Fisharebest\Webtrees\Http\RequestHandlers\EditRawRecordAction::class
            => ['action' => 'edit_raw_record_save', 'type' => 'edit'],
        \Fisharebest\Webtrees\Http\RequestHandlers\EditNotePage::class
            => ['action' => 'edit_note_form', 'type' => 'edit'],
        \Fisharebest\Webtrees\Http\RequestHandlers\EditNoteAction::class
            => ['action' => 'edit_note_save', 'type' => 'edit'],
        \Fisharebest\Webtrees\Http\RequestHandlers\DeleteRecord::class
            => ['action' => 'delete_record', 'type' => 'edit'],
        \Fisharebest\Webtrees\Http\RequestHandlers\DeleteFact::class
            => ['action' => 'delete_fact', 'type' => 'edit'],

        // Kategorie 4: Erstellung
        \Fisharebest\Webtrees\Http\RequestHandlers\CreateMediaObjectAction::class
            => ['action' => 'create_media', 'type' => 'edit'],
        \Fisharebest\Webtrees\Http\RequestHandlers\CreateNoteAction::class
            => ['action' => 'create_note', 'type' => 'edit'],
        \Fisharebest\Webtrees\Http\RequestHandlers\CreateSourceAction::class
            => ['action' => 'create_source', 'type' => 'edit'],
        \Fisharebest\Webtrees\Http\RequestHandlers\CreateRepositoryAction::class
            => ['action' => 'create_repository', 'type' => 'edit'],
        \Fisharebest\Webtrees\Http\RequestHandlers\CreateLocationAction::class
            => ['action' => 'create_location', 'type' => 'edit'],
        \Fisharebest\Webtrees\Http\RequestHandlers\CreateSubmitterAction::class
            => ['action' => 'create_submitter', 'type' => 'edit'],
        \Fisharebest\Webtrees\Http\RequestHandlers\AddNewFact::class
            => ['action' => 'add_fact_form', 'type' => 'edit'],
        \Fisharebest\Webtrees\Http\RequestHandlers\AddUnlinkedAction::class
            => ['action' => 'create_individual', 'type' => 'edit'],

        // Kategorie 5: Beziehungen
        \Fisharebest\Webtrees\Http\RequestHandlers\AddChildToIndividualPage::class
            => ['action' => 'add_child_form', 'type' => 'edit'],
        \Fisharebest\Webtrees\Http\RequestHandlers\AddChildToIndividualAction::class
            => ['action' => 'add_child_save', 'type' => 'edit'],
        \Fisharebest\Webtrees\Http\RequestHandlers\AddSpouseToIndividualPage::class
            => ['action' => 'add_spouse_form', 'type' => 'edit'],
        \Fisharebest\Webtrees\Http\RequestHandlers\AddSpouseToIndividualAction::class
            => ['action' => 'add_spouse_save', 'type' => 'edit'],
        \Fisharebest\Webtrees\Http\RequestHandlers\AddParentToIndividualPage::class
            => ['action' => 'add_parent_form', 'type' => 'edit'],
        \Fisharebest\Webtrees\Http\RequestHandlers\AddParentToIndividualAction::class
            => ['action' => 'add_parent_save', 'type' => 'edit'],
        \Fisharebest\Webtrees\Http\RequestHandlers\AddChildToFamilyAction::class
            => ['action' => 'add_child_to_family', 'type' => 'edit'],
        \Fisharebest\Webtrees\Http\RequestHandlers\AddSpouseToFamilyAction::class
            => ['action' => 'add_spouse_to_family', 'type' => 'edit'],
        \Fisharebest\Webtrees\Http\RequestHandlers\LinkChildToFamilyAction::class
            => ['action' => 'link_child_to_family', 'type' => 'edit'],
        \Fisharebest\Webtrees\Http\RequestHandlers\LinkSpouseToIndividualAction::class
            => ['action' => 'link_spouse', 'type' => 'edit'],

        // Kategorie 6: Navigation & Berichte
        \Fisharebest\Webtrees\Http\RequestHandlers\CalendarPage::class
            => ['action' => 'calendar', 'type' => 'query'],
        \Fisharebest\Webtrees\Http\RequestHandlers\ReportListPage::class
            => ['action' => 'report_list', 'type' => 'query'],
        \Fisharebest\Webtrees\Http\RequestHandlers\ReportGenerate::class
            => ['action' => 'report_generate', 'type' => 'query'],
        \Fisharebest\Webtrees\Http\RequestHandlers\ContactPage::class
            => ['action' => 'contact_form', 'type' => 'query'],
        \Fisharebest\Webtrees\Http\RequestHandlers\PendingChanges::class
            => ['action' => 'pending_changes', 'type' => 'query'],
    ];

    public function title(): string
    {
        return 'OTel Spans';
    }

    public function description(): string
    {
        return 'OpenTelemetry semantic spans for webtrees testing platform';
    }

    public function customModuleVersion(): string
    {
        return '1.0.0';
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        // Graceful degradation: skip tracing if OTel SDK is not loaded
        if (!class_exists(Globals::class)) {
            return $handler->handle($request);
        }

        $route = Validator::attributes($request)->route();
        $routeName = $route->name ?? '';
        $mapping = self::ROUTE_MAP[$routeName] ?? null;

        if ($mapping === null) {
            return $handler->handle($request);
        }

        $tracer = Globals::tracerProvider()->getTracer('otel-spans');
        $span = $tracer->spanBuilder('webtrees.' . $mapping['action'])
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->startSpan();

        $scope = $span->activate();

        try {
            // Semantische Attribute
            $span->setAttribute('webtrees.action', $mapping['action']);
            $span->setAttribute('webtrees.type', $mapping['type']);
            $span->setAttribute('webtrees.route', $this->shortName($routeName));
            $span->setAttribute('http.method', $request->getMethod());

            // Tree und XREF (optional)
            $tree = Validator::attributes($request)->treeOptional();
            if ($tree !== null) {
                $span->setAttribute('webtrees.tree', $tree->name());
            }
            $xref = Validator::attributes($request)->string('xref', '');
            if ($xref !== '') {
                $span->setAttribute('webtrees.xref', $xref);
            }

            // Baggage --> Span-Attribute
            $baggage = Baggage::getCurrent();
            $runId = $baggage->getEntry('test.run_id');
            if ($runId !== null) {
                $span->setAttribute('test.run_id', $runId->getValue());
            }
            $caseId = $baggage->getEntry('test.case_id');
            if ($caseId !== null) {
                $span->setAttribute('test.case_id', $caseId->getValue());
            }

            $response = $handler->handle($request);

            $span->setAttribute('http.status_code', $response->getStatusCode());
            $span->setStatus(
                $response->getStatusCode() >= 400
                    ? StatusCode::STATUS_ERROR
                    : StatusCode::STATUS_OK
            );

            return $response;
        } finally {
            $span->end();
            $scope->detach();
        }
    }

    private function shortName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);
        return str_replace('::class', '', end($parts));
    }
}
