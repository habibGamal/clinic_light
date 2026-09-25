<?php

declare(strict_types=1);

namespace App\Services\Reception;

use App\Models\Patient;
use Illuminate\Database\Eloquent\Collection;

final class PatientSearchService
{
    /**
     * @return array{found: bool, patient: Patient|null}
     */
    public function findByPhone(?string $phone): array
    {
        $phone = mb_trim((string) $phone);

        if (empty($phone)) {
            return ['found' => false, 'patient' => null];
        }

        $patient = Patient::query()
            ->where('phone', $phone)
            ->first();

        return [
            'found' => (bool) $patient,
            'patient' => $patient,
        ];
    }

    /**
     * @return Collection<int, Patient>
     */
    public function search(?string $query = null, ?string $phone = null, ?string $name = null): Collection
    {
        $query = mb_trim((string) $query);
        $phone = mb_trim((string) $phone);
        $name = mb_trim((string) $name);

        $patientsQuery = Patient::query()->withCount('visits');

        if (! empty($phone)) {
            $patientsQuery->where('phone', 'like', "%{$phone}%");
        }

        if (! empty($name)) {
            $patientsQuery->where('full_name', 'like', "%{$name}%");
        }

        if (! empty($query) && empty($phone) && empty($name)) {
            $patientsQuery->where(function ($q) use ($query): void {
                $q->where('full_name', 'like', "%{$query}%")
                    ->orWhere('phone', 'like', "%{$query}%");
            });
        }

        return $patientsQuery
            ->with([
                'visits' => function ($vq): void {
                    $vq->with(['referringDoctor', 'invoice', 'visitServices.service'])
                        ->orderByDesc('visit_date')
                        ->limit(10);
                },
            ])
            ->limit(15)
            ->get();
    }
}
