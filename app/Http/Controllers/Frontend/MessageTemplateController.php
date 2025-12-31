<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
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

        if ($request->status === 'default') {
            MessageTemplate::where('user_id', Auth::id())
                ->where('status', 'default')
                ->update(['status' => 'active']);
        }

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
        $messageTemplate = MessageTemplate::findOrFail($id);

        $this->authorizeTemplate($messageTemplate);

        $request->validate([
            'name'    => 'required|string|unique:message_templates,name,' . $messageTemplate->id . ',id,user_id,' . Auth::id(),
            'status' => 'required|in:default,active,inactive',
            'message' => 'required|string',
        ]);

        if ($request->status === 'default') {
            MessageTemplate::where('user_id', Auth::id())
                ->where('id', '!=', $messageTemplate->id)
                ->where('status', 'default')
                ->update(['status' => 'active']);
        }

        $messageTemplate->update($request->only('name', 'status', 'message'));

        return response()->json([
            'success' => true,
            'data' => $messageTemplate,
        ]);
    }


    public function destroy($id)
    {
        $messageTemplate = MessageTemplate::findOrFail($id);

        $this->authorizeTemplate($messageTemplate);

        if ($messageTemplate->status === 'default') {
            return response()->json([
                'success' => false,
                'message' => 'Default template cannot be deleted',
            ], 403);
        }

        $messageTemplate->delete();

        return response()->json([
            'success' => true,
            'message' => 'Template deleted successfully',
        ]);
    }


    public function default_template()
    {
        $defaultTemplate = MessageTemplate::where('user_id', Auth::id())
            ->where('status', 'default')
            ->first();

        if (!$defaultTemplate) {
            return response()->json([
                'success' => false,
                'message' => 'No default template found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $defaultTemplate,
        ]);
    }


    private function authorizeTemplate(MessageTemplate $template)
    {
        abort_if($template->user_id !== Auth::id(), 403, 'Unauthorized');
    }
}
