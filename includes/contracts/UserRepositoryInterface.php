<?php

interface UserRepositoryInterface {
    public function findById(int $id);
    public function findByEmail(string $email);
    public function allActive();
    public function create(array $data);
    public function update(int $id, array $data);
    public function changePassword(int $id, string $hash);
}
