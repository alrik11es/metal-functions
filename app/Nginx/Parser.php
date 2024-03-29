<?php
namespace App\Nginx;

class Parser
{
    public $input;     // from where do we get tokens?
    public $tokenized = [];
    public $lookahead; // the current lookahead token

    public function __construct(Lexer $input)
    {
        $this->input = $input;
        $this->consume();
    }

    /** If lookahead token type matches x, consume & return else error */
    public function match($x)
    {
        if ($this->lookahead->type == $x) {
            $this->consume();
        } else {
            throw new \Exception("Se espera token " .
                $this->input->getTokenName($x) .
                ". Se ha encontrado: " . $this->lookahead);
        }
    }

    public function consume()
    {
        do{
            $next_token = $this->input->nextToken();
            $this->lookahead = $next_token;
            $this->tokenized[] = $next_token;
        } while ($next_token->type != Lexer::EOF);
    }

    public function parse()
    {
        $this->parsed = $this->recursiveParsing([], 0);
        $result = [$this->parsed[1][0]];
        return $result;
    }

    private function recursiveParsing($parent, $start)
    {
        for ($i=$start; $i<count($this->tokenized); $i++) {
            $token = $this->tokenized[$i];
            if ($token->type == Lexer::LBRACKET) {
                $base = $this->tokenized[$i-1];
                array_pop($parent);
                $class = new \stdClass();
                $class->expr = $base->text;
                $i++;
                list($i, $children) = $this->recursiveParsing([], $i);
                $class->children = $children;
                $parent[] = $class;
            } else if ($token->type == Lexer::RBRACKET) {
                break;
            } else if ($token->text != '<EOF>') {
                $parent[] = $token->text;
            }
        }
        return [$i, $parent];
    }

    public function build($config)
    {
        $r = [];
        $level = 0;
        $this->recursiveBuilding($config, $r, $level);
        $r[] = '';
        return implode("\n",$r);
    }

    private function recursiveBuilding($arr, &$result, &$level)
    {
        $separator = "\t";
        foreach ($arr as $line) {
            $tabs = str_repeat($separator, $level);
            if(is_object($line)) {
                $result[] = $tabs.trim($line->expr);
                $result[array_key_last($result)] = $result[array_key_last($result)].' {';
                $level++;
                $this->recursiveBuilding($line->children, $result, $level);
                $level--;
                $result[] = $tabs.'}';
            } else {
                $result[] = $tabs.trim($line);
            }
        }
    }
}
