<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Memo;
use App\Models\Tag;
use App\Models\MemoTag;
use DB;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        $tags = Tag::where('user_id', '=', \Auth::id())->whereNull('deleted_at')->orderBy('id', 'DESC')->get();
        return view('create', compact('tags'));
    }

    public function store(Request $request)
    {
        $posts=$request->all();
        $request->validate(['content'=>'required']);
        // トランザクション開始
        DB::transaction(function () use ($posts) {
            // メモIDをインサートして取得
            $memo_id=Memo::insertGetId(['content'=>$posts['content'], 'user_id'=>\Auth::id()]);
            $tag_exists=Tag::where('user_id', '=', \Auth::id())->where('name', '=', $posts['new_tag'])
            ->exists();
            // 新規タグが入力されているかチェック
            // 新規タグが既にtagsテーブルに存在するのかをチェック
            if (!empty($posts['new_tag']) && !$tag_exists) {
                // 新規タグが既に存在しなければ、tagsテーブルにインサートIDを取得
                $tag_id=Tag::insertGetId(['user_id'=>\Auth::id(),'name'=>$posts['new_tag']]);
                // memo_tagsにインサートして、目元タグを紐付ける
                MemoTag::insert(['memo_id'=>$memo_id,'tag_id'=>$tag_id]);
            }
            foreach ($posts['tags'] as $tag) {
                MemoTag::insert(['memo_id'=>$memo_id, 'tag_id'=>$tag]);
            }
        });
        // dump dieの略→メソッドの引数をとった値を展開して止める→データ確認

        return redirect(route('home'));
    }

    public function edit($id)
    {
        $edit_memo=Memo::select('memos.*', 'tags.id AS tag_id')
        ->leftJoin('memo_tags', 'memo_tags.memo_id', "=", 'memos.id')
        ->leftJoin('tags', 'memo_tags.tag_id', "=", 'tags.id')
        ->where('memos.user_id', '=', \Auth::id())
        ->where('memos.id', '=', $id)
        ->whereNull('memos.deleted_at')
        ->get();

        $include_tags=[];
        foreach ($edit_memo as $memo) {
            array_push($include_tags, $memo['tag_id']);
        }
        $tags = Tag::where('user_id', '=', \Auth::id())->whereNull('deleted_at')->orderBy('id', 'DESC')->get();
        return view('edit', compact('edit_memo', 'include_tags', 'tags'));
    }

    public function update(Request $request)
    {
        $posts=$request->all();
        $request->validate(['content'=>'required']);
        // dump dieの略→メソッドの引数をとった値を展開して止める→データ確認
        
        DB::transaction(function () use ($posts) {
            Memo::where('id', $posts['memo_id'])->update(['content'=>$posts['content'], 'user_id'=>\Auth::id()]);
            MemoTag::where('memo_id', '=', $posts['memo_id'])->delete();
            foreach ($posts['tags'] as $tag) {
                MemoTag::insert(['memo_id'=>$posts['memo_id'],'tag_id'=>$tag]);
            }
            $tag_exists=Tag::where('user_id', '=', \Auth::id())->where('name', '=', $posts['new_tag'])
            ->exists();
            // 新規タグが入力されているかチェック
            // 新規タグが既にtagsテーブルに存在するのかをチェック
            if (!empty($posts['new_tag']) && !$tag_exists) {
                // 新規タグが既に存在しなければ、tagsテーブルにインサートIDを取得
                $tag_id=Tag::insertGetId(['user_id'=>\Auth::id(),'name'=>$posts['new_tag']]);
                // memo_tagsにインサートして、目元タグを紐付ける
                MemoTag::insert(['memo_id'=>$posts['memo_id'],'tag_id'=>$tag_id]);
            }
        });
        

        return redirect(route('home'));
    }

    public function destroy(Request $request)
    {
        $posts=$request->all();
        // dump dieの略→メソッドの引数をとった値を展開して止める→データ確認
        Memo::where('id', $posts['memo_id'])->update(['deleted_at'=>date("Y-m-d H:i:s", time())]);

        return redirect(route('home'));
    }
}
