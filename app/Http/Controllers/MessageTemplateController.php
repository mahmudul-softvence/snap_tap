<?php

namespace App\Http\Controllers;

use App\Models\MessageTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MessageTemplateController extends Controller
{
    public function index()
    {
        $templates = Auth::user()->messageTemplates()->latest()->get();

        return response()->json([
            'success' => true,
            'data' => $templates,
        ]);
    }

    public function create(Request $request)
    {
        $request->validate([
            'name'    => 'required|string|unique:message_templates,name,NULL,id,user_id,' . Auth::id(),
            'status' => 'required|in:default,active,inactive',
            'message' => 'required|string',
        ]);

        $template = MessageTemplate::create([
            'user_id' => Auth::id(),
            'name'    => $request->name,
            'status'  => $request->status,
            'message' => $request->message,
        ]);

        return response()->json([
            'success' => true,
            'data' => $template,
        ], 201);
    }


    public function show($id)
    {
        $messageTemplate = MessageTemplate::find($id);

        $this->authorizeTemplate($messageTemplate);

        return response()->json([
            'success' => true,
            'data' => $messageTemplate,
        ]);
    }


    public function update(Request $request, $id)
    {

        $messageTemplate = MessageTemplate::find($id);

        $this->authorizeTemplate($messageTemplate);

        $request->validate([
            'name'    => 'required|string|unique:message_templates,name,' . $messageTemplate->id . ',id,user_id,' . Auth::id(),
            'status' => 'required|in:default,active,inactive',
            'message' => 'required|string',
        ]);

        $messageTemplate->update($request->only('name', 'status', 'message'));

        return response()->json([
            'success' => true,
            'data' => $messageTemplate,
        ]);
    }

    public function destroy($id)
    {
        $messageTemplate = MessageTemplate::find($id);

        $this->authorizeTemplate($messageTemplate);

        $messageTemplate->delete();

        return response()->json([
            'success' => true,
            'message' => 'Template deleted successfully',
        ]);
    }


    private function authorizeTemplate(MessageTemplate $template)
    {
        abort_if($template->user_id !== Auth::id(), 403, 'Unauthorized');
    }
}
