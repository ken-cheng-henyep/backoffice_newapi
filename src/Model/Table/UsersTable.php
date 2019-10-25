<?php
// src/Model/Table/UsersTable.php
namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class UsersTable extends Table
{

    public function validationDefault(Validator $validator)
    {
        return $validator
            ->notEmpty('username', 'A username is required')
            ->notEmpty('password', 'A password is required')
            ->add('password', 'length', ['rule' => ['lengthBetween', 8, 100],
                'message' => 'Password should be at least 8 characters'
            ])
            ->add('password', 'custom_wc', [
                'rule' => function ($value, $context) {
                    // Custom logic that returns true/false
                    return checkValidWeCollectPassword($value);
                },
                'message' => 'New password must contain at least 1 numeric character, 1 uppercase letter and 1 lowercase letter'
            ])
            ->notEmpty('role', 'A role is required')
            ->add('role', 'inList', [
                'rule' => ['inList', ['admin', 'operator', 'manager']],
                'message' => 'Please enter a valid role'
            ]);
    }

}