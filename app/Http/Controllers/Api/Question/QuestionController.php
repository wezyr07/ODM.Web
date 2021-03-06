<?php
/**
 *  Bu yazılım Elektrik Elektronik Teknolojileri Alanı/Elektrik Öğretmeni Hakan GÜLEN tarafından geliştirilmiş olup
 *  geliştirilen bütün kaynak kodlar
 *  Creative Commons Attribution-NonCommercial-ShareAlike 4.0 International (CC BY-NC-SA 4.0) ile lisanslanmıştır.
 *   Ayrıntılı lisans bilgisi için https://creativecommons.org/licenses/by-nc-sa/4.0/legalcode.tr sayfasını ziyaret edebilirsiniz.2019
 */

/**
 * Bu yazılım Elektrik Elektronik Teknolojileri Alanı/Elektrik Öğretmeni Hakan GÜLEN tarafından geliştirilmiş olup geliştirilen bütün kaynak kodlar
 * Creative Commons Attribution-NonCommercial-ShareAlike 4.0 International (CC BY-NC-SA 4.0) ile lisanslanmıştır.
 * Ayrıntılı lisans bilgisi için https://creativecommons.org/licenses/by-nc-sa/4.0/legalcode.tr sayfasını ziyaret edebilirsiniz.2019
 */

namespace App\Http\Controllers\Api\Question;

use App\Http\Controllers\ApiController;
use App\Http\Controllers\Utils;
use App\Http\Controllers\Utils\ResponseKeys;
use App\Models\Branch;
use App\Models\LearningOutcome;
use App\Models\Question;
use App\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class QuestionController extends ApiController
{
    /**
     * Soru oluşturma api fonk.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function create(Request $request)
    {

        $validationResult = $this->apiValidator($request, [
            'learning_outcome_id' => 'required',
            'difficulty' => 'required',
            'question_file' => 'required|mimes:pdf|max:1024'
        ]);
        if ($validationResult) {
            return response()->json($validationResult, 422);
        }

        $question = $request->all();
        $lesson_id = $question["lesson_id"];
        if (!isset($lesson_id))
            $lesson_id = Auth::user()->branch_id;
        $lo_id = $question["learning_outcome_id"];
        $difficulty = $question["difficulty"];
        $keywords = $question["keywords"];
        $correct_answer = $question["correct_answer"];
        $question_file = $question["question_file"];

        try {
            DB::beginTransaction();
            $question = new Question();
            $question->lesson_id = $lesson_id;
            $question->learning_outcome_id = $lo_id;
            $question->difficulty = $difficulty;
            $question->correct_answer = $correct_answer;
            $question->keywords = $keywords;
            $question->creator_id = Auth::id();
            $isSaved = $question->save();
            $lo = LearningOutcome::findOrFail($lo_id)->code;
            $loCode = str_replace(".", "-", $lo);
            if ($isSaved) {
                $code = Branch::find($lesson_id)->code;
                if ($question_file !== null) {
                    $expl = explode(".", $question_file->getClientOriginalName());
                    $ext = end($expl);

                    //Tüm dosyalar ana klasör altındaki storage->app->public altına ekleniyot
                    //Path formatı: public/Kazanım kodu-kullanıcı id-soru id-dosya uzantısı
                    //örn: public/T-7-4-2-31-73.pdf
                    $path = 'public/' . $loCode . $question->creator_id . '-' . $question->id . '.' . $ext;
                    Storage::put($path, file_get_contents($question_file->getPathName()));
                    $question->content_url = $path;
                    $question->save();
                }
            }
            DB::commit();
        } catch (Exception $exception) {
            DB::rollBack();
            Storage::delete($path);
            return response()->json($this->apiException($exception), 500);
        }
        return response()->json([ResponseKeys::MESSAGE => "Soru ekleme işlemi başarılı."], 201);
    }

    public function findById($id)
    {
//      $question = Question::findOrFail($id);
        $question = DB::table("questions as q")
            ->join("users as u", "u.id", "=", "q.creator_id")
            ->join("branches as b", "b.id", "=", "q.lesson_id")
            ->join("learning_outcomes as lo", "lo.id", "=", "q.learning_outcome_id")
            ->where("q.id", $id)
            ->select(
                "q.id",
                "q.creator_id",
                "u.branch_id",
                "q.keywords",
                "q.difficulty",
                "q.correct_answer",
                "q.status",
                DB::raw("CASE
                                WHEN status = 0 THEN 'İşleme alınmamış'
                                WHEN status = 1 THEN 'Değerlendirme aşamasında'
                                WHEN status = 2 THEN 'Sorulamayacak soru'
                                WHEN status = 3 THEN 'Revizyon gerekli'
                                WHEN status = 4 THEN 'Revizyon tamamlanmış'
                                WHEN status = 5 THEN 'Havuza girmiş'
                               END AS status_title"),
                DB::raw("DATE_FORMAT(q.created_at, '%d.%m.%Y') as created_at"),
                DB::raw("CONCAT(lo.code, ' ', lo.content) as learning_outcome"),
                "lo.class_level",
                "u.full_name as creator",
                "b.name as branch",
                DB::raw("(SELECT IF(COUNT(id) >= 1, true, false) FROM question_delete_requests as qdr WHERE qdr.question_id = q.id) as has_delete_request")
            )
            ->first();
        if (isset($question)) {
            return response()->json($question, 200);
        }
        return response()->json([ResponseKeys::MESSAGE => "Böyle bir soru yok!"], 404);
    }

    public function findByContentAndClassLevelAndBranch(Request $request)
    {
        $validationResult = $this->apiValidator($request, [
            'class_level' => 'required'
        ]);
        if ($validationResult) {
            return response()->json($validationResult, 422);
        }
        $user = Auth::user();
        $branch_id = $request->query('branch_id');
        if (!isset($branch_id))
            $branch_id = $user->branch_id;
        $class_level = $request->query('class_level');
        $searched_content = $request->query('searched_content');
        //TODO sosyalciler için düzenleme yapılacak
        if ($user->isAn('admin') || $user->isAn('elector')) {
            $res = DB::select("SELECT q.id, q.creator_id, q.keywords, lo.code, lo.content FROM questions as q
                                INNER JOIN learning_outcomes lo on q.learning_outcome_id = lo.id
                                WHERE lo.class_level = :class_level AND
                                      q.lesson_id = :lesson_id AND
                                      (q.keywords like CONCAT('%', :sc1, '%') ||
                                       lo.content like CONCAT('%', :sc2, '%') ||
                                       lo.code like CONCAT(:sc3, '%'))",
                [
                    "class_level" => $class_level,
                    "lesson_id" => $branch_id,
                    "sc1" => $searched_content,
                    "sc2" => $searched_content,
                    "sc3" => $searched_content,
                ]);
            return response()->json($res, 200);
        }
        //TODO Sosyalciler için bir düzenleme yapılacak
        if ($user->isAn('teacher')) {
            $id = $user->id;
            // PArametre sayısı aynı olmazsa ve eşsiz parametre adı olmazsa hata basıypor
            $res = DB::select("SELECT q.id, q.creator_id, q.keywords, lo.code, lo.content FROM questions as q
                                INNER JOIN learning_outcomes lo on q.learning_outcome_id = lo.id
                                WHERE q.creator_id = :user_id AND
                                      lo.class_level = :class_level AND
                                      q.lesson_id = :lesson_id AND
                                      (q.keywords like CONCAT('%', :sc1, '%') ||
                                       lo.content like CONCAT('%', :sc2, '%') ||
                                       lo.code like CONCAT(:sc3, '%'))",
                [
                    "user_id" => $id,
                    "class_level" => $class_level,
                    "lesson_id" => $branch_id,
                    "sc1" => $searched_content,
                    "sc2" => $searched_content,
                    "sc3" => $searched_content,
                ]);
            return response()->json($res, 200);
        }
        return response()->json([ResponseKeys::MESSAGE => "Hiçbir şey bulamadık!"], 404);

    }

    public function getLastQuestions($size)
    {
        $user = Auth::user();
        if ($user->isAn('admin')) {
            $res = DB::table("questions as q")
                ->join("learning_outcomes as l", "l.id", "=", "q.learning_outcome_id")
                ->orderBy("q.created_at", "desc")
                ->take($size)
                ->select("q.id", "q.creator_id", "q.keywords", "l.code", "l.content")
                ->get();
            return response()->json($res, 200);
        }
        if ($user->isAn('elector')) {
            //TODO sadece benim sorularım diyerek sorgulama yapabilmeli değerlendirici
            $branch_id = $user->branch_id;
            $res = DB::table("questions as q")
                ->join("learning_outcomes as l", "l.id", "=", "q.learning_outcome_id")
                ->orderBy("q.created_at", "desc")
                ->take($size)
                ->select("q.id", "q.creator_id", "q.keywords", "l.code", "l.content");
            if ($user->branch->code === 'SB') {
                $brancIds = Branch::where('code', 'SB')
                    ->orWhere('code', 'İTA')
                    ->get('id');
                $res->whereIn("q.lesson_id", $brancIds);
            }
            return response()->json($res->get(), 200);
        }
        if ($user->isAn('teacher')) {
            $id = $user->id;
            // PArametre sayısı aynı olmazsa ve eşsiz parametre adı olmazsa hata basıypor
            $res = DB::table("questions as q")
                ->join("learning_outcomes as l", "l.id", "=", "q.learning_outcome_id")
                ->where("q.creator_id", $id)
                ->orderBy("q.created_at", "desc")
                ->take($size)
                ->select("q.id", "q.creator_id", "q.keywords", "l.code", "l.content")
                ->get();
            return response()->json($res, 200);
        }
        return response()->json([ResponseKeys::MESSAGE => "Hiçbir şey bulamadık!"], 200);

    }

    public function getFile($id)
    {
        $question = Question::findOrFail($id);
        $filePath = $question->content_url;
        if (isset($filePath)) {
            if (!Storage::exists($filePath)) {
                return response()->json([ResponseKeys::MESSAGE => "Belirttiğiniz soruya ait bir dosya yok!"], 404);
            }
            $pdfContent = Storage::get($filePath);
            $encodedPDF = "data:application/pdf;base64," . base64_encode($pdfContent);
            return response($encodedPDF, 200);
        }
        return response()->json([ResponseKeys::MESSAGE => "Veritabanına dosya yolu kaydı girilmemiş!"], 404);
    }
}
