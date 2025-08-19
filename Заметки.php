Перейди в папку с проектом:

cd /D H:\Programs\OSPanel\home\chopchop.local
cd H:\Programs\OSPanel\home\chopchop.local

Запусти сборку стилей:

npm run dev


…или создайте новый репозиторий в командной строке
echo "# tg-support" >> README.md
git init
git add README.md
git commit -m "первый коммит"
git branch -M main
git remote add origin https://github.com/KAPITOSHKA7400/chopchop.git
git push -u origin main

Чтобы “обновить” репозиторий на GitHub после того, как вы внесли новые правки, достаточно обычного цикла «add → commit → push». Например:

Добавить измененные файлы в индекс

bash

git add .
Закоммитить изменения с описанием

bash

git commit -m "Краткое описание изменений"
Запушить в удалённый репозиторий

bash

git push
Если вы пушите в ту же ветку, что и раньше (main), дополнительных параметров не нужно.


Способ 1: Используй обычную tree без параметров
Открой командную строку (cmd), перейди в корень проекта и выполни:

cmd

cd H:\Programs\OSPanel\home\chopchop.local

tree /f > project_structure.txt

Это создаст файл project_structure.txt со всей структурой проекта. После этого:











H:\Programs\OSPanel\home\chopchop.local>php artisan make:migration alter_body_default_on_bot_msg_templates --table=bot_msg_templates

INFO  Migration [H:\Programs\OSPanel\home\chopchop.local\database\migrations/2025_08_03_224824_alter_body_default_on_bot_msg_templates.php] created successfully.


H:\Programs\OSPanel\home\chopchop.local>php artisan migrate

INFO  Running migrations.

2025_08_03_224824_alter_body_default_on_bot_msg_templates .............................................. 1.78ms FAIL

Illuminate\Database\QueryException

SQLSTATE[42000]: Syntax error or access violation: 1101 BLOB, TEXT, GEOMETRY or JSON column 'body' can't have a default value (Connection: mysql, SQL: alter table `bot_msg_templates` modify `body` text not null default '')

at vendor\laravel\framework\src\Illuminate\Database\Connection.php:824
820▕                     $this->getName(), $query, $this->prepareBindings($bindings), $e
821▕                 );
822▕             }
823▕
➜ 824▕             throw new QueryException(
825▕                 $this->getName(), $query, $this->prepareBindings($bindings), $e
826▕             );
827▕         }
828▕     }

1   vendor\laravel\framework\src\Illuminate\Database\Connection.php:564
PDOException::("SQLSTATE[42000]: Syntax error or access violation: 1101 BLOB, TEXT, GEOMETRY or JSON column 'body' can't have a default value")

2   vendor\laravel\framework\src\Illuminate\Database\Connection.php:564
PDO::prepare("alter table `bot_msg_templates` modify `body` text not null default ''")

