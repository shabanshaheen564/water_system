<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\Dataset;
use App\Models\DatasetRecord;
use App\Models\GisFeature;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AuditObserver
{
    public function created(Model $model): void
    {
        $this->write($model, 'created', [], $this->snapshot($model));
    }

    public function updated(Model $model): void
    {
        $this->write($model, 'updated', $this->snapshotOriginal($model), $this->snapshot($model));
    }

    public function deleted(Model $model): void
    {
        $this->write($model, 'deleted', $this->snapshotOriginal($model), []);
    }

    private function write(Model $model, string $action, array $old, array $new): void
    {
        if ($model instanceof AuditLog) {
            return;
        }

        $datasetId = $model->dataset_id ?? ($model instanceof DatasetRecord ? $model->dataset_id : null);

        if ($model instanceof GisFeature) {
            $datasetId = $model->dataset_id;
            $old['geometry'] = $this->geometryGeoJson($model->getRawOriginal('geometry'));
            $new['geometry'] = $action === 'deleted'
                ? null
                : $this->geometryGeoJson($model->getAttributes()['geometry'] ?? null);
        }

        $layerName = $datasetId
            ? Dataset::whereKey($datasetId)->value('display_name')
            : class_basename($model);

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'auditable_type' => $model::class,
            'auditable_id' => $model->getKey(),
            'dataset_id' => $datasetId,
            'layer_name' => $layerName,
            'old_values' => $old,
            'new_values' => $new,
            'metadata' => [
                'route' => request()?->route()?->getName(),
                'method' => request()?->method(),
            ],
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }

    private function snapshot(Model $model): array
    {
        return $this->sanitize($model->getAttributes());
    }

    private function snapshotOriginal(Model $model): array
    {
        return $this->sanitize($model->getRawOriginal());
    }

    private function sanitize(array $values): array
    {
        unset($values['password'], $values['remember_token']);

        foreach ($values as $key => $value) {
            if (is_resource($value)) {
                $values[$key] = '[binary]';
            } elseif (is_object($value)) {
                $values[$key] = '[object]';
            }
        }

        return $values;
    }

    private function geometryGeoJson($geometry): ?array
    {
        if ($geometry === null) {
            return null;
        }

        try {
            $row = DB::selectOne('SELECT ST_AsGeoJSON(?) AS geojson', [$geometry]);

            return $row?->geojson ? json_decode($row->geojson, true) : null;
        } catch (\Throwable) {
            return ['type' => 'Geometry', 'value' => '[unavailable]'];
        }
    }
}
