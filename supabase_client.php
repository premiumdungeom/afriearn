<?php
// supabase_client.php

class SupabaseClient {
    private $supabase_url;
    private $supabase_key;
    private $access_token;
    
    public function __construct($url, $key) {
        $this->supabase_url = $url;
        $this->supabase_key = $key;
    }
    
    public function setAccessToken($token) {
        $this->access_token = $token;
    }
    
    // Change from private to public so SupabaseTable can access it
    public function makeRequest($endpoint, $method = 'GET', $data = null) {
        $url = $this->supabase_url . $endpoint;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . $this->supabase_key,
            'Authorization: Bearer ' . ($this->access_token ?: $this->supabase_key),
            'Content-Type: ' . ($method === 'GET' ? 'application/json' : 'application/json'),
            'Prefer: return=representation'
        ]);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method === 'PUT' || $method === 'PATCH') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return [
                'data' => ['error' => $error],
                'status' => 500
            ];
        }
        
        return [
            'data' => json_decode($response, true),
            'status' => $httpCode
        ];
    }
    
    // Auth methods
    public function signUp($email, $password, $userData = []) {
        $endpoint = '/auth/v1/signup';
        $data = [
            'email' => $email,
            'password' => $password,
            'data' => $userData
        ];
        
        return $this->makeRequest($endpoint, 'POST', $data);
    }
    
    public function signIn($email, $password) {
        $endpoint = '/auth/v1/token?grant_type=password';
        $data = [
            'email' => $email,
            'password' => $password
        ];
        
        $result = $this->makeRequest($endpoint, 'POST', $data);
        
        if (isset($result['data']['access_token'])) {
            $this->setAccessToken($result['data']['access_token']);
            $_SESSION['access_token'] = $result['data']['access_token'];
            $_SESSION['user'] = $result['data']['user'];
        }
        
        return $result;
    }
    
    public function signInWithUsername($username, $password) {
        // First get email from username
        $email = $this->getEmailFromUsername($username);
        if ($email) {
            return $this->signIn($email, $password);
        }
        return ['data' => ['error' => 'User not found'], 'status' => 404];
    }
    
    private function getEmailFromUsername($username) {
        $endpoint = "/rest/v1/users?username=eq.{$username}&select=email";
        $result = $this->makeRequest($endpoint);
        
        if (!empty($result['data'][0]['email'])) {
            return $result['data'][0]['email'];
        }
        return null;
    }
    
    public function signOut() {
        $endpoint = '/auth/v1/logout';
        $result = $this->makeRequest($endpoint, 'POST');
        
        session_destroy();
        $this->access_token = null;
        
        return $result;
    }
    
    // Database methods
    public function from($table) {
        return new SupabaseTable($this, $table);
    }
}

class SupabaseTable {
    private $client;
    private $table;
    
    public function __construct($client, $table) {
        $this->client = $client;
        $this->table = $table;
    }
    
    public function select($columns = '*') {
        $endpoint = "/rest/v1/{$this->table}?select=" . urlencode($columns);
        return $this->client->makeRequest($endpoint);
    }
    
    public function insert($data) {
        $endpoint = "/rest/v1/{$this->table}";
        return $this->client->makeRequest($endpoint, 'POST', $data);
    }
    
    public function update($data, $column = null, $value = null) {
        $endpoint = "/rest/v1/{$this->table}";
        if ($column && $value) {
            $endpoint .= "?{$column}=eq.{$value}";
        }
        return $this->client->makeRequest($endpoint, 'PATCH', $data);
    }
    
    public function eq($column, $value) {
        $endpoint = "/rest/v1/{$this->table}?{$column}=eq.{$value}";
        return $this->client->makeRequest($endpoint);
    }
    
    public function delete($column = null, $value = null) {
        $endpoint = "/rest/v1/{$this->table}";
        if ($column && $value) {
            $endpoint .= "?{$column}=eq.{$value}";
        }
        return $this->client->makeRequest($endpoint, 'DELETE');
    }
}

// Initialize Supabase
$supabase_url = 'https://hlnhavgeibngpwchjzzv.supabase.co';
$supabase_key = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImhsbmhhdmdlaWJuZ3B3Y2hqenp2Iiwicm9sZSI6ImFub24iLCJpYXQiOjE3NjQyMjg0NzksImV4cCI6MjA3OTgwNDQ3OX0.TwXeEg_gLuEKvpQneoJqcrmFqCQhdbnkqy5mXv0S4EI';
$supabase = new SupabaseClient($supabase_url, $supabase_key);

// Set session token if exists
if (isset($_SESSION['access_token'])) {
    $supabase->setAccessToken($_SESSION['access_token']);
}
?>