<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AgencyRequest;
use App\Models\Agency;
use App\Models\WaterBody;
use App\Support\MunicipalityRouting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AgencyController extends Controller
{
    public function index(Request $request): View
    {
        $f = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'active' => ['nullable', 'in:0,1'], 'verification' => ['nullable', 'in:provisional,verified']]);
        $q = Agency::query()->with('waterBodies:id,name');
        if (filled($f['q'] ?? null)) {
            $q->where(fn ($q) => $q->where('name', 'like', '%'.$f['q'].'%')->orWhere('municipality', 'like', '%'.$f['q'].'%')->orWhere('email', 'like', '%'.$f['q'].'%'));
        }
        foreach (['active' => 'active', 'verification' => 'verification_status'] as $key => $col) {
            if (isset($f[$key]) && $f[$key] !== '') {
                $q->where($col, $f[$key]);
            }
        }

        return view('admin.agencies.index', ['agencies' => $q->orderBy('municipality')->paginate(15)->withQueryString(), 'filters' => $f,
            'municipalityBodies' => WaterBody::supported()->orderBy('name')->get(['name', 'municipality'])->groupBy('municipality')]);
    }

    public function create(): View
    {
        return $this->form(new Agency);
    }

    public function edit(Agency $agency): View
    {
        return $this->form($agency);
    }

    private function form(Agency $agency): View
    {
        return view('admin.agencies.form', ['agency' => $agency, 'waterBodies' => WaterBody::supported()->orderBy('name')->get(['id', 'name', 'municipality']),
            'municipalities' => MunicipalityRouting::municipalities(),
            'selected' => $agency->exists ? $agency->waterBodies()->pluck('water_bodies.id')->all() : []]);
    }

    public function store(AgencyRequest $request): RedirectResponse
    {
        $this->save($request, new Agency);

        return to_route('admin.agencies.index')->with('directory_saved', 'Recipient added.');
    }

    public function update(AgencyRequest $request, Agency $agency): RedirectResponse
    {
        $this->save($request, $agency);

        return to_route('admin.agencies.index')->with('directory_saved', 'Recipient updated.');
    }

    private function save(AgencyRequest $request, Agency $agency): void
    {
        DB::transaction(function () use ($request, $agency): void {
            $data = $request->validated();
            $ids = $data['water_body_ids'] ?? [];
            unset($data['water_body_ids'], $data['barangay']);
            $data['email'] = mb_strtolower($data['email']);
            $agency->forceFill($data)->save();
            $agency->waterBodies()->sync($ids);
        });
    }

    public function destroy(Request $request, Agency $agency): RedirectResponse
    {
        $request->validate(['confirm_delete' => ['accepted']]);
        $agency->delete();

        return to_route('admin.agencies.index')->with('directory_saved', 'Recipient deleted. Referral history is preserved.');
    }
}
