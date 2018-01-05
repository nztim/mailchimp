<?php

use NZTim\Mailchimp\Member;
use PHPUnit\Framework\TestCase;

class MemberTest extends TestCase
{
    private $email = 'test@example.com';

    /**
     * @test
     * @expectedException InvalidArgumentException
     */
    public function valid_email_address_required()
    {
        new Member('invalid');
    }

    /** @test */
    public function default_values()
    {
        $member = new Member($this->email);
        $this->assertEquals(md5($this->email), $member->hash());
        $this->assertEquals(['email_address' => $this->email, 'status_if_new' => 'pending'], $member->parameters());
    }

    /**
     * @test
     * @expectedException InvalidArgumentException
     */
    public function email_type_invalid()
    {
        (new Member($this->email))->email_type('invalid');
    }

    /** @test */
    public function email_type()
    {
        $member = (new Member($this->email))->email_type('html');
        $this->assertEquals('html', $member->parameters()['email_type']);
    }

    /**
     * @test
     * @expectedException InvalidArgumentException
     */
    public function status_invalid()
    {
        (new Member($this->email))->status('invalid');
    }

    /** @test */
    public function status()
    {
        $member = (new Member($this->email))->status('cleaned');
        $this->assertEquals('cleaned', $member->parameters()['status']);
    }

    /** @test */
    public function confirm_true()
    {
        $member = (new Member($this->email))->confirm(true);
        $this->assertEquals('pending', $member->parameters()['status']);
        $this->assertEquals('pending', $member->parameters()['status_if_new']);
    }

    /** @test */
    public function confirm_false()
    {
        $member = (new Member($this->email))->confirm(false);
        $this->assertEquals('subscribed', $member->parameters()['status']);
        $this->assertEquals('subscribed', $member->parameters()['status_if_new']);
    }

    /** @test */
    public function merge_fields()
    {
        $merge = ['a' => 'b'];
        $member = (new Member($this->email))->merge_fields($merge);
        $this->assertEquals($merge, $member->parameters()['merge_fields']);
    }

    /** @test */
    public function interests()
    {
        $interests = ['a' => 'b'];
        $member = (new Member($this->email))->interests($interests);
        $this->assertEquals($interests, $member->parameters()['interests']);
    }

    /**
     * @test
     * @expectedException InvalidArgumentException
     */
    public function language_invalid()
    {
        (new Member($this->email))->language('invalid');
    }

    /** @test */
    public function language()
    {
        $member = (new Member($this->email))->language('en');
        $this->assertEquals('en', $member->parameters()['language']);
    }

    /** @test */
    public function vip()
    {
        $member = (new Member($this->email))->vip(true);
        $this->assertEquals(true, $member->parameters()['vip']);
    }

    /** @test */
    public function location()
    {
        $member = (new Member($this->email))->location(-36.786992, 174.858770);
        $this->assertEquals(-36.786992, $member->parameters()['location']['latitude']);
        $this->assertEquals(174.858770, $member->parameters()['location']['longitude']);
    }

//    /** @test */
//    public function ip_signup()
//    {
//        $member = (new Member($this->email))->ip_signup('8.8.8.8');
//        $this->assertEquals('8.8.8.8', $member->parameters()['ip_signup']);
//    }
//
//    /** @test */
//    public function timestamp_signup()
//    {
//        $member = (new Member($this->email))->timestamp_signup('1515181003');
//        $this->assertEquals('1515181003', $member->parameters()['timestamp_signup']);
//    }
//
//    /** @test */
//    public function ip_opt()
//    {
//        $member = (new Member($this->email))->ip_opt('8.8.8.8');
//        $this->assertEquals('8.8.8.8', $member->parameters()['ip_opt']);
//    }
//
//    /** @test */
//    public function timestamp_opt()
//    {
//        $member = (new Member($this->email))->timestamp_opt('1515181003');
//        $this->assertEquals('1515181003', $member->parameters()['timestamp_opt']);
//    }
}
