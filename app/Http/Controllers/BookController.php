<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\BookDownload;
use App\Models\BookReview;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BookController extends Controller
{
    public function index()
    {
        $books = Book::with('category', 'editorial', 'authors')->orderBy('title', 'asc')->get();
        // $books = Book::orderBy('title', 'asc')->get();
        return $this->getResponse200($books);
    }

    public function store(Request $request)
    {
        $isbn = trim($request->isbn);
        $existIsbn = Book::where('isbn', $isbn)->exists();
        if (!$existIsbn) {
            $book = new Book();
            $book->isbn = $isbn;
            $book->title = $request->title;
            $book->description = $request->description;
            $book->published_date = Carbon::now();
            $book->category_id = $request->category['id'];
            $book->editorial_id = $request->editorial['id'];
            $book->save();
            foreach ($request->authors as $item) {
                $book->authors()->attach($item);
            }
            return $this->getResponse201("Book", "Created", $book);
        } else {
            return $this->getResponse400();
        }
    }

    public function update(Request $request, $id)
    {
        $book = Book::find($id);

        DB::beginTransaction();
        try {

            if ($book) {
                $isbn = trim($request->isbn);
                $isbnOwner = Book::where('isbn', $isbn)->first();
                if (!$isbnOwner || $isbnOwner->id == $book->id) {
                    $book->isbn = $isbn;
                    $book->title = $request->title;
                    $book->description = $request->description;
                    $book->published_date = Carbon::now();
                    $book->category_id = $request->category['id'];
                    $book->editorial_id = $request->editorial['id'];
                    $book->update();
                    //Delete
                    foreach ($book->authors as $item) {
                        $book->authors()->detach($item->id);
                    }
                    //Add new authors
                    if ($request->authors) {
                        foreach ($request->authors as $item) {
                            $book->authors()->attach($item);
                        }
                    }

                    $book = Book::with('category', 'editorial', 'authors')->where('id', $id)->get();
                    return $this->getResponse201("Book", "Updated", $book);
                } else {
                    return $this->getResponse400();
                }
            } else {
                return $this->getResponse404();
            }

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            return $this->getResponse500([$e->getMessage()]);
        }
    }

    public function show($id)
    {
        // $book = Book::find($id);
        $book = Book::with('category', 'editorial', 'authors', 'bookDownload')->where('id', $id)->get();
        if ($book) {
            return $this->getResponse200($book);
        } else {
            return $this->getResponse404();
        }
    }

    public function destroy($id)
    {
        $book = Book::find($id);
        if($book == null){
            return $this->getResponse404();
        }

        DB::beginTransaction();
        try {

            $reviews = BookReview::where('book_id', $id)->delete();
            $book->bookDownload()->delete();
            $book->authors()->detach();
            $book->delete();

            DB::commit();
            return $this->getResponseDelete200("Book");
        } catch (Exception $e) {
            DB::rollBack();
            return $this->getResponse500([$e->getMessage()]);
        }

    }

    public function download($id){
        $book = Book::find($id);
        if ($book != null) {
            $bookDownload = $book->bookDownload()->get()->first();
            if($bookDownload == null){
                $bookDownload = new BookDownload();
                $bookDownload->book_id = $book->id;
                $bookDownload->total_downloads = 1;
                $bookDownload->save();
            }else{
                $bookDownload->total_downloads =  $bookDownload->total_downloads + 1;
                $bookDownload->update();
            }
            return $this->show($id);
        } else {
            return $this->getResponse404();
        }
    }


    public function addBookReview(Request $request){
            
        $validator = Validator::make($request->all(), ['comment' => 'required','book_id' => 'required']);
        if($validator->fails()){
            return $this->getResponse500([$validator->errors()]);
        }

        
        DB::beginTransaction();
        try {

            $user = User::find(auth()->user()->id);
            $book = Book::find($request->book_id);
            if ($book == null) {
                return $this->getResponse404();
            }

            $bookReview = new BookReview();
            $bookReview->comment = $request->comment;
            $bookReview->edited  = false;
            $bookReview->book_id = $request->book_id;
            $bookReview->user_id = $user->id;
            $bookReview->save();

            DB::commit();
            return response()->json(['message' =>'Your comment has been successfully created'], 201);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->getResponse500([$e->getMessage()]);
        }
    
    }

    public function updateBookReview(Request $request, $id){

        $validator = Validator::make($request->all(), ['comment' => 'required']);
        if($validator->fails()){
            return $this->getResponse500([$validator->errors()]);
        }

        
        DB::beginTransaction();
        try {

            $bookReview = BookReview::find($id);
            if ($bookReview == null) {
                return $this->getResponse404();
            }

            if($bookReview->user_id != auth()->user()->id){
                return $this->getResponse403();
            }

            $bookReview->comment = $request->comment;
            $bookReview->edited  = true;
            $bookReview->update();

            DB::commit();
            return response()->json(['message' =>'Your comment has been successfully updated'], 201);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->getResponse500([$e->getMessage()]);
        }
    }
}
