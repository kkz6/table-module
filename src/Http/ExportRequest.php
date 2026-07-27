<?php

declare(strict_types=1);

namespace Modules\Table\Http;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Table\Export;
use Modules\Table\Exports\ExportFormat;

class ExportRequest extends FormRequest
{
    use ResolvesTableInstance;

    private ?Export $resolvedExport = null;

    /**
     * Merge the route parameters into the request.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'table'  => $this->route('table'),
            'name'   => $this->route('name'),
            'export' => $this->route('export'),
            'state'  => $this->route('state'),
            'keys'   => collect(explode(',', $this->query('keys', '')))
                ->filter()
                ->values()
                ->all(),
        ]);
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->getExport()->isAuthorized($this);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $baseRules = [
            'table'  => ['required', 'string', new Base64EncodedTableClassRule],
            'name'   => ['required', 'string', 'min:1', 'max:255'],
            'export' => ['required', 'integer', 'min:0'],
            'state'  => ['nullable', 'string'],
            'keys'   => ['nullable', 'array', 'min:0'],
        ];

        $export = $this->getExport();

        if (! $export->hasExporter()) {
            return $baseRules;
        }

        $formats = array_map(fn (ExportFormat $format): string => $format->value, $export->getFormats());

        return array_merge($baseRules, [
            'columnMap'             => [$export->hasColumnMapping() ? 'required' : 'nullable', 'array'],
            'columnMap.*'           => ['array'],
            'columnMap.*.isEnabled' => ['boolean'],
            'columnMap.*.label'     => ['nullable', 'string', 'max:255'],
            'formats'               => ['required', 'array', 'min:1'],
            'formats.*'             => ['string', Rule::in($formats)],
            'options'               => ['nullable', 'array'],
        ]);
    }

    /**
     * Get the Export instance.
     */
    public function getExport(): Export
    {
        if ($this->resolvedExport !== null) {
            return $this->resolvedExport;
        }

        $table = $this->getTable();

        $export = $table->getExportById((int) $this->route('export'));

        abort_unless($export instanceof Export, 404);

        $export->setTable($table);

        return $this->resolvedExport = $export;
    }
}
