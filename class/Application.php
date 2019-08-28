<?php

class Application extends Config {

    private $routingRules = [
        'Application' => [
            'index' => 'Application/actionIndex'
        ],
        'robots.txt' => [
            'index' => 'Application/actionRobots'
        ],
        'debug' => [
            'index' => 'Application/actionDebug'
        ]
    ];

    /**
     * @var $view View
     */
    private $view;

    function __construct() {
        parent::__construct();
        $this->view = new View($this);
        if ($this->requestMethod == 'POST') {
            header('Content-Type: application/json');
            die(json_encode($this->ajaxHandler($_POST)));
        } else {
            //Normal GET request. Nothing to do yet
        }
    }

    public function run() {
        if (array_key_exists($this->routing->controller, $this->routingRules)) {
            if (array_key_exists($this->routing->action, $this->routingRules[$this->routing->controller])) {
                list($controller, $action) = explode(DIRECTORY_SEPARATOR, $this->routingRules[$this->routing->controller][$this->routing->action]);
                call_user_func([$controller, $action]);
            } else { http_response_code(404); die('action not found'); }
        } else { http_response_code(404); die('controller not found'); }
    }

    public function actionIndex() {
        return $this->view->render('index');
    }

    public function actionDebug() {
        return $this->view->render('debug');
    }

    public function actionRobots() {
        return implode(PHP_EOL, ['User-Agent: *', 'Disallow: /']);
    }

    /**
     * Здесь нужно реализовать механизм валидации данных формы
     * @param $data array
     * $data - массив пар ключ-значение, генерируемое JavaScript функцией serializeArray()
     * name - Имя, обязательное поле, не должно содержать цифр и не быть больше 64 символов
     * phone - Телефон, обязательное поле, должно быть в правильном международном формате. Например +38 (067) 123-45-67
     * email - E-mail, необязательное поле, но должно быть либо пустым либо содержать валидный адрес e-mail
     * comment - необязательное поле, но не должно содержать тэгов и быть больше 1024 символов
     *
     * @return array
     * Возвращаем массив с обязательными полями:
     * result => true, если данные валидны, и false если есть хотя бы одна ошибка.
     * error => ассоциативный массив с найдеными ошибками,
     * где ключ - name поля формы, а значение - текст ошибки (напр. ['phone' => 'Некорректный номер']).
     * в случае отсутствия ошибок, возвращать следует пустой массив
     */
    public function actionFormSubmit($data) {

        $errors = [];                                  //Отсутствие ошибок

        // Обязательные поля
        $errors['name'] = 'Имя обязательно';
        $errors['phone'] = 'Телефон обязательный';

        foreach ($data as $field) {
            // Пустое значение пропускаем
            if (empty($field['value'])) {
                continue;
            }
            switch ($field['name']) {
                case 'name': $error = $this->validateName($field['value']); unset($errors['name']); break;
                case 'phone': $error = $this->validatePhone($field['value']); unset($errors['phone']); break;
                case 'email': $error = $this->validateEmail($field['value']); break;
                case 'comment': $error = $this->validateComment($field['value']); break;
            }
            if (!empty($error)) {
                $errors[$field['name']] = $error;
            }
        }

        // Проверка соотвествия формы
        $formFields = ['name', 'phone', 'email', 'comment'];
        foreach ($data as $key => $field) {
            if(in_array($field['name'], $formFields)) {
                $formFields = array_diff($formFields, [$field['name']]);
            } else {
                // Убераем лишние поля из $data
                unset($data[$key]);
            }
        }
        // Обработка недостающих полей
        $missingFields = array_fill_keys($formFields, 'Непорядок, поле отсутствует в $data');
        $errors = array_merge($errors, $missingFields);

        return ['result' => count($errors) === 0, 'error' => $errors];
    }


    /**
     * Функция обработки AJAX запросов
     * @param $post
     * @return array
     */
    private function ajaxHandler($post) {
        if (count($post)) {
            if (isset($post['method'])) {
                switch($post['method']) {
                    case 'formSubmit': $result = $this->actionFormSubmit($post['data']);
                        break;
                    default: $result = ['error' => 'Unknown method']; break;
                }
            } else { $result = ['error' => 'Unspecified method!']; }
        } else { $result = ['error' => 'Empty request!']; }
        return $result;
    }

    /**
     * Валидация имени
     * @param $value
     * @return string
     */
    private function validateName($value) {
        $error = '';
        if (preg_match('/[0-9]/', $value) || strlen($value) > 64) {
            $error = 'Некорректное имя';
        }
        return $error;
    }

    /**
     * Валидация телефона
     * @param $value
     * @return string
     */
    private function validatePhone($value) {
        $error = '';
        $value = str_replace([' ', '(', ')', '-', '+'], '', $value);
        if (!filter_var($value, FILTER_VALIDATE_INT) || strlen($value) < 10) {
            $error = 'Некорректный телефон';
        }
        return $error;
    }

    /**
     * Валидация e-mail
     * @param $value
     * @return string
     */
    private function validateEmail($value) {
        return filter_var($value, FILTER_VALIDATE_EMAIL) ? '' : 'Некорректный E-mail';
    }

    /**
     * Валидация комментария
     * @param $value
     * @return string
     */
    private function validateComment($value) {
        $error = '';
        if (strlen($value) < 1024) {
            $error = 'Комментарий должен быть больше 1024 символа';
        } else {
            if (strip_tags($value) != $value) {
                $error = 'Некорректный комментарий';
            }
        }
        return $error;
    }
}
