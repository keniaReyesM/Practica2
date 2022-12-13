<?php

namespace App\Http\Controllers;

use App\Models\Author;
use App\Models\Book;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthorController extends Controller
{
    public function index()
    {
        //Para solo obtener los authores
        // $authors = Author::all();

        //Para obtener los authores con los libros
        $authors = Author::with('books')->get();

        return $this->getResponse200($authors);
    }

    public function store(Request $request)
    {
        $author = new Author();
        $author->name = $request->name;
        $author->first_surname = $request->first_surname;
        $author->second_surname = $request->second_surname;
        if ($author->save()) {
            return $this->getResponse201("author", "Created", $author);
        } else {
            return $this->getResponse400();
        }
    }

    public function update(Request $request, $id)
    {
        $author = Author::find($id);

        DB::beginTransaction();
        try {
            if ($author) {
                $author->name = $request->name;
                $author->first_surname = $request->first_surname;
                $author->second_surname = $request->second_surname;
                $author->update();
                DB::commit();
                return $this->getResponse201("Author", "Updated", $author);
            } else {
                return $this->getResponse404();
            }
        } catch (Exception $e) {
            DB::rollBack();
            return $this->getResponse500([$e->getMessage()]);
        }
    }

    public function show($id)
    {
        $author = Author::find($id);
        if ($author) {
            
            //Para obtener el author con sus librosbro.
            $author['books'] = $author->books;

            return $this->getResponse200($author);
        }else{
            return $this->getResponse404();
        }
    }

    public function destroy($id)
    {
        $author = Author::find($id);
        if ($author != null) {
            $author->books()->detach();
            $author->delete();
            return $this->getResponseDelete200("author");
        }else {
            return $this->getResponse404();
        }
    }

}
