# composite-model
PHP class providing CRUD operations on a group of related SQL tables


## Synopsis

Currently this project just contains a PHP class used to perform CRUD operations on a base table and it's related tables. This was ripped straight from a Zend framework and is currently depending on the Zend Db classes. 

## Todo

- provide code examples for installation and use
- add SQL injection prevention measures, IE quote query parameters
- add unit testing
- remove the Zend Db dependencies (possibly)


## Motivation

I wanted to consolidate a group of related tables that together represent one object, in order to simplify CRUD operations and abstract away database implementations. 


## License

The MIT License (MIT)

Copyright (c) [2010] [Christopher E. Purvis]

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.