<?php

namespace Modules\DisposableBasic\Http\Controllers;

use App\Contracts\Controller;
use App\Models\Aircraft;
use App\Models\Pirep;
use App\Models\Subfleet;

class DB_FleetController extends Controller
{
    // Fleet
    public function index()
    {
        $aircraft = Aircraft::with('subfleet.airline')->orderby('icao')->orderby('registration')->paginate(25);

        return view('DBasic::fleet.index', [
            'aircraft' => $aircraft,
            'units'    => array('fuel' => setting('units.fuel'), 'weight' => setting('units.weight')),
        ]);
    }

    // Subfleet
    public function subfleet($subfleet_type)
    {
        $units = array('fuel' => setting('units.fuel'), 'weight' => setting('units.weight'));
        $subfleet = Subfleet::withCount('flights', 'fares')->with('airline', 'fares')->where('type', $subfleet_type)->first();
        $aircraft = Aircraft::withCount('simbriefs')->with('subfleet.airline')->where('subfleet_id', $subfleet->id)->orderby('registration')->paginate(25);

        if (!$subfleet) {
            flash()->error('Subfleet not found !');
            return redirect(route('DBasic.fleet'));
        }

        return view('DBasic::fleet.index', [
            'aircraft' => $aircraft,
            'subfleet' => $subfleet,
            'units'    => $units,
        ]);
    }

    // Aircraft
    public function aircraft($ac_reg)
    {
        $units = array('fuel' => setting('units.fuel'), 'weight' => setting('units.weight'));
        $eager_load = array('airport', 'files', 'subfleet.airline', 'subfleet.fares', 'subfleet.hub');
        $aircraft = Aircraft::with($eager_load)->where('registration', $ac_reg)->first();

        if (!$aircraft) {
            flash()->error('Aircraft not found !');
            return redirect(route('DBasic.fleet'));
        }

        // Latest Pireps
        $where = array('aircraft_id' => $aircraft->id, 'state' => 2, 'status' => 'ONB');
        $eager_pireps = array('dpt_airport', 'arr_airport', 'user', 'airline');
        $pireps = Pirep::with($eager_pireps)->where($where)->orderby('submitted_at', 'desc')->take(8)->get();

        // Aircraft or Subfleet Image
        $image_ac = strtolower('image/aircraft/'.$aircraft->registration.'.jpg');
        $image_sf = strtolower('image/subfleet/'.optional($aircraft->subfleet)->type.'.jpg');

        if (is_file($image_ac)) {
            $image = $image_ac;
            $image_text = optional(optional($aircraft->subfleet)->airline)->name.' '.$aircraft->registration;
        } elseif (is_file($image_sf)) {
            $image = $image_sf;
            $image_text = optional(optional($aircraft->subfleet)->airline)->name.' '.$aircraft->icao;
        }

        // Passenger Weight
        $pax_weight = setting('simbrief.noncharter_pax_weight');
        if ($units['weight'] === 'kg') {
            $pax_weight = round($pax_weight / 2.20462262185, 2);
        }

        return view('DBasic::fleet.show', [
            'aircraft'   => $aircraft,
            'image'      => isset($image) ? $image : null,
            'image_text' => isset($image_text) ? $image_text : null,
            'pax_weight' => $pax_weight,
            'pireps'     => $pireps,
            'units'      => $units,
        ]);
    }
}
