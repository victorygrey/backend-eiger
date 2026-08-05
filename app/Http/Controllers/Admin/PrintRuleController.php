<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePrintRuleRequest;
use App\Models\PrintRule;
use Illuminate\Http\Request;

class PrintRuleController extends Controller
{
    public function index()
    {
        $rules = PrintRule::latest()->paginate(10)->withQueryString();
        return view('admin.print-rules.index', compact('rules'));
    }

    public function edit(PrintRule $printRule)
    {
        return view('admin.print-rules.edit', compact('printRule'));
    }

    public function update(UpdatePrintRuleRequest $request, PrintRule $printRule)
    {
        $data = $request->validated();
        $data['require_membership'] = $request->has('require_membership');
        $data['enabled']            = $request->has('enabled');

        $printRule->update($data);

        return redirect()->route('admin.print-rules.index')
            ->with('success', 'Print Rule berhasil diperbarui.');
    }
}
